# Assessment Creation — Design Spec
**Date:** 2026-03-29
**Status:** Approved

---

## Overview

Add the ability for field assessors to start a new facility assessment directly from the mobile app (React + Capacitor), with full offline support matching the existing data layer patterns.

---

## Approach

FAB (Floating Action Button) on the Assessments list screen opens a bottom-sheet form. On submit the app creates an assessment online or queues creation offline, then immediately opens the assessment form so the user can begin filling sections.

---

## Architecture

### New Files
- `src/screens/screen-new-assessment.jsx` — bottom-sheet form component (`NewAssessmentSheet`)

### Modified Files
| File | Change |
|---|---|
| `src/services/api.service.js` | Add `assessments.create` to `_rawApi` and offline-aware `api`; add `facilities` offline cache; extend `prefetchForOffline` |
| `src/services/sync-queue.js` | Add handler for `assessments.create` op type including temp→real ID migration |
| `src/services/offline-store.js` | Add `facilities` store (DB version bump to 3) |
| `src/screens/screen-assessments-list.jsx` | Add FAB button; accept and wire `onCreate` + `facilities` props |
| `src/App.jsx` | Pass `facilities` state + `onCreate` callback; handle new assessment in state |

---

## Data Flow

```
User taps FAB on Assessments screen
  → NewAssessmentSheet slides up
  → Loads facility list (api.facilities.list → IndexedDB cache)
  → User selects facility, type, date → taps "Start Assessment"

      [online]
        POST /api/v1/assessments
        → 201: save assessment to offline store → add to app state → open form modal
        → 409: show inline "duplicate" error with "Open Existing" action

      [offline]
        Generate tempId = "offline_" + Date.now()
        Build provisional assessment (all known fields, status: "in_progress",
          section_progress from cached schema)
        Save to IndexedDB assessments store under tempId
        Enqueue { type: "assessments.create", tempId, facility_id, assessment_type, assessment_date }
        Add to app state → open form modal immediately

  → When connectivity returns, sync queue replays assessments.create:
      POST /api/v1/assessments
      → 201: migrate tempId data (responses, HR, HP) to realId in IndexedDB
             dispatch CustomEvent("assessment:id-resolved", { tempId, realId })
             App.jsx swaps tempId → realId in assessments state
      → 409: use returned existing assessment's ID, discard temp record
```

---

## UI Components

### FAB
- Circular button, bottom-right of Assessments list, above bottom nav (z-index above list, below nav)
- `T.gradientPrimary` fill, white `+` icon, `T.shadowMd` glow
- Tapping opens `NewAssessmentSheet`

### NewAssessmentSheet
Bottom-sheet overlay (absolutely positioned, slides up via CSS transform animation) with a drag handle and three fields:

1. **Facility picker**
   - Search input filters cached facility list in real time
   - Each result shows: facility name (bold) + MFL code + subcounty
   - Offline with no cache: shows a dismissable warning banner, still allows proceeding
   - Tapping a result selects it and collapses the results list

2. **Assessment type**
   - 3-pill segmented control: Baseline · Midline · Endline
   - Defaults to Baseline
   - Maps to backend values: `baseline | midline | endline`

3. **Assessment date**
   - Native `<input type="date">` defaulting to today
   - `max` attribute set to today (enforces `before_or_equal:today`)

4. **Submit button** — "Start Assessment"
   - Disabled until all three fields are filled
   - Shows spinner while API call is in-flight
   - On 409 duplicate: inline error message + "Open Existing" link

---

## Offline Handling

### Facility Cache
- New IndexedDB store: `facilities` (DB version 3)
- `offlineStore.getFacilities()` / `saveFacilities(list)`
- `api.facilities.list()` becomes offline-aware: try network → cache on success → return cache on network failure
- Added to `api.prefetchForOffline` so facilities are available before going into the field

### Provisional Assessment (offline create)
- `tempId = "offline_" + Date.now()`
- Provisional object fields: `{ id: tempId, facility_id, facility_name, mfl_code, county, subcounty, assessment_type, assessment_date, assessor_id, status: "in_progress", section_progress, _isOffline: true }`
- `section_progress` derived from cached schema (all section codes → `false`)
- Saved to `offlineStore.saveAssessment(provisional)`
- Enqueued in sync queue as `assessments.create`

### Sync Queue: `assessments.create` Op
```
op shape: {
  type: "assessments.create",
  tempId,           // "offline_<timestamp>"
  facility_id,
  assessment_type,
  assessment_date
}
```

Replay logic:
1. `POST /api/v1/assessments` via `_rawApi.assessments.create`
2. On **201**: get `realId` from response
   - Copy IndexedDB records: responses, hr, hp from `tempId` → `realId`
   - Delete `tempId` records
   - `dispatch(new CustomEvent("assessment:id-resolved", { detail: { tempId, realId } }))`
3. On **409**: extract `assessment` from response body → use its ID as `realId`, same migration
4. On other errors: leave in queue, retry next flush

### App.jsx: ID Resolution
- Listens for `assessment:id-resolved` CustomEvent
- Swaps `tempId → realId` in `assessments` state array
- If form modal is open with `tempId`, updates `modal.data.id` to `realId`

---

## API Changes

### `_rawApi.assessments.create`
```js
create: (facility_id, assessment_type, assessment_date) =>
  post('/assessments', { facility_id, assessment_type, assessment_date })
```

### `api.assessments.create` (offline-aware)
```js
create: async (facility_id, assessment_type, assessment_date, facilityMeta) => {
  try {
    const data = await _rawApi.assessments.create(facility_id, assessment_type, assessment_date);
    const a = data?.assessment ?? data?.data ?? data;
    if (a?.id) await offlineStore.saveAssessment(a);
    return data;
  } catch (e) {
    if (isNetworkError(e)) {
      // provisional creation — see offline flow above
    }
    throw e;
  }
}
```

### `api.facilities.list` (upgraded to offline-aware)
Currently delegates straight to `_rawApi`. Upgraded to cache results and serve from cache on network failure.

---

## Backend Contract (existing, no changes needed)

```
POST /api/v1/assessments
Body: { facility_id, assessment_type, assessment_date }
201: { message, assessment: AssessmentResource }
409: { message, assessment: AssessmentResource }  ← existing duplicate
422: { errors }  ← validation failure
```

---

## Error States

| Scenario | Handling |
|---|---|
| No facility selected | Submit button disabled |
| No facilities in cache (offline) | Warning banner; submit still allowed if user knows facility_id (edge case — acceptable to block with clear message) |
| Network error on create | Offline flow: provisional assessment, queue |
| 409 duplicate | Inline error + "Open Existing" CTA |
| 422 validation | Show field-level errors inline |
| Sync replay failure | Op stays in queue; SyncIndicator shows pending count |

---

## Out of Scope
- Editing an existing assessment's header fields (facility, type, date) post-creation — handled by existing `PUT /assessments/:id`
- Deleting assessments from the app — endpoint exists but no UI needed now
- Role-based creation restrictions — backend `store` authenticates by token only; any logged-in user may create
