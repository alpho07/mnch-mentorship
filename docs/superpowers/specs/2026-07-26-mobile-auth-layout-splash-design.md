# Mobile App — Auth, Layout Safe-Area, and Cold-Start Splash — Design Spec
**Date:** 2026-07-26
**Status:** Approved

---

## Overview

Four independent pieces of work on the `public/m-assessment-app` React/Capacitor app:

- **A. Auth** — Register and Forgot Password screens, mirroring `admin/register` and `admin/password-reset/request`, with email links deep-linking back into the Android app via verified App Links.
- **B. Layout safe-area fix** — a shared z-index scale and safe-area-aware bottom offset, applied to every fixed-position bottom element app-wide, fixing the mentorships FAB and the Class Report back button along the way.
- **C. Content/UX fixes** — remove the module-count badge on program picker cards; fix the mentees-count input so it's editable and validates 2–8.
- **D. Cold-start splash** — replace the plain "Loading…" text in `ScopeShell` with a branded full-screen splash while the first scope's data loads.

No changes to the offline-store schema, sync-queue, or existing scope architecture.

---

## A. Auth: Register + Forgot Password

### Backend (Laravel, `app/Http/Controllers/Api/AuthController.php` + `routes/api.php`)

New endpoints under the existing unauthenticated `auth` route group:

- `POST /api/v1/auth/register` — mirrors `App\Livewire\Auth\CustomRegister::register()`:
  - Accepts `first_name`, `middle_name` (optional), `last_name`, `email`, `phone`, `cadre_id`, `department_id`, `role` (`mentee` default | `facility_mentor`), `county_id`, `facility_id`.
  - No CAPTCHA (mobile app is a trusted client, not a public web form).
  - No password field — creates the user with `status: 'pending'` and a random unusable password, assigns the role, syncs county/facility, and sends `AccountVerificationMail` exactly as the web flow does. Returns a simple success payload (no token — account isn't active yet).
- `GET /api/v1/auth/verify-account/{user}?expires=...&signature=...` and `POST /api/v1/auth/verify-account/{user}` (set password) — API equivalents of the existing public `/account/verify/{user}` web page (`AccountVerificationController`), reusing its signed-URL validation (`expires`/`signature` query params, same as the web route — not a separate token scheme), so the app can complete verification natively instead of rendering the web Blade view.
- Existing `forgot-password` / `reset-password` endpoints are reused as-is (no backend change needed there — they already accept email/token/password, matching the `token`+`email` query params used by the web confirm page at `/admin/set-password/{token}`).

### Android App Links (deep linking)

- Host `public/.well-known/assetlinks.json` on the Laravel domain, listing the app's package (`com.mnch.mentorship.app`) and release-keystore SHA-256 fingerprint.
- Add a verified `<intent-filter android:autoVerify="true">` to `AndroidManifest.xml` for `https://mnchkenyamentorship.org/account/verify/*` (registration verification, signed URL) and `https://mnchkenyamentorship.org/admin/set-password/*` (forgot-password confirm, `CustomResetPassword` Livewire page).
- These stay the *only* email links — same URLs work in a plain browser (existing Blade/Livewire pages, unchanged) and, when the app is installed, open the app directly instead.
- In `App.jsx`, add a Capacitor `App.addListener('appUrlOpen', ...)` handler that parses the incoming URL's path/query (`expires`/`signature` for account verify, `token`/`email` for password reset) and routes to `screen-set-password.jsx` with those values pre-filled.

### Mobile screens (`src/screens/`)

- **`screen-register.jsx`** — new. Same section grouping as `admin/register` (Personal Information → Contact Details → Professional Information → Geographic Scope), reusing `SearchableDropdown`/`Field` patterns already established in `screen-mentorship-form.jsx`. Cadre/department/county/facility options come from the existing `api.lookups.*` calls already used elsewhere in the app — no new lookup endpoints needed. On submit, calls the new `api.auth.register(...)`, then shows a persistent success state: "Check your email — tap the link to set your password," with a link back to Login.
- **`screen-forgot-password.jsx`** — new, two-step: (1) email entry → `api.auth.forgotPassword(email)`; (2) after the deep link lands (or the user manually re-opens the app), a "Set new password" step using the token/email captured from the deep link, calling `api.auth.resetPassword(...)`.
- **`screen-set-password.jsx`** — new, shared step used both by the register-verification deep link and the forgot-password deep link (same "enter new password twice" UI, different submit target).
- `screen-login.jsx` — wire the existing dead "Forgot password?" button to open `screen-forgot-password.jsx`; add a "Don't have an account? Register" link below it opening `screen-register.jsx`. Both are local `App.jsx`-level screens (siblings of `LoginScreen`), not nested inside `ScopeShell`, since they're pre-auth.

### `api.service.js`

Add to both `_rawApi.auth` and the wrapped `api.auth`:
```
register: (payload) => post('/auth/register', payload),
verifyAccount: (userId, expires, signature, password, password_confirmation) =>
    post(`/auth/verify-account/${userId}`, { expires, signature, password, password_confirmation }),
```
(`forgotPassword`/`resetPassword` already exist and are reused unchanged.)

---

## B. Layout safe-area systemic fix

### `constants.js`

Add:
```js
export const Z = {
  navBar: 40,   // in-app bottom tab bar (per-scope)
  fab: 45,      // floating action buttons — always above their own scope's navBar
  header: 100,  // ScopeShell sticky header (scope tabs)
  sheet: 200,   // full-screen sheets/modals (must beat header when they cover the top)
  toast: 300,   // sync toast, topmost transient UI
};

// bottom offset that clears both this app's own fixed bottom nav (navHeight, default 64)
// and the OS gesture/button nav bar (env(safe-area-inset-bottom))
T.bottomSafe = (navHeight = 64) => `calc(${navHeight}px + env(safe-area-inset-bottom, 0px))`;
```

### Sweep (apply `Z.*` and `T.bottomSafe(...)`)

- `screen-mentorships-list.jsx` FAB: `bottom: 80` → `bottom: T.bottomSafe(80)`, `zIndex: 10` → `Z.fab`.
- `screen-assessments-list.jsx` FAB: same treatment (currently also a bare `bottom: 80`).
- `AssessmentsScope.jsx` / `TrainingsScope.jsx` / `MentorshipsScope.jsx` `BottomNav`: keep `bottom: 0`, keep existing `paddingBottom: env(safe-area-inset-bottom)`, standardize `zIndex` to `Z.navBar` (was 50) — this also fixes a second latent bug: the mentorships FAB was `zIndex: 10`, actually *below* its own nav bar's `zIndex: 50`, so on any vertical overlap the nav already painted over it regardless of offset. `Z.fab` (45) `> Z.navBar` (40) fixes both issues together.
- `screen-class-detail.jsx` (`ClassReportSheet`) and `screen-module-detail.jsx`: `zIndex: 60`/`50`/`51` → `Z.sheet` (200), so they paint above `ScopeShell`'s header (100).
- `screen-mentee-manager.jsx`, `screen-mentorship-detail.jsx` sheets, `ScopeShell` profile sheet: already at 200/300 — leave as-is, just alias to `Z.sheet`/`Z.toast` for consistency.
- `ScopeShell.jsx` sticky header: explicit `zIndex: Z.header` (100) — unchanged value, now named.
- Any fixed footer bar with a primary action (e.g. `MentorshipFormScreen`'s step footer, `ClassFormScreen`, `screen-new-assessment.jsx`) gets `paddingBottom: env(safe-area-inset-bottom, 0px)` added to its existing padding so the OS gesture bar can't sit flush against the button on any device.

This is a mechanical, low-risk sweep — no component restructuring, just style-value substitution — but it touches most screen files, so it's reviewed as one batch.

---

## C. Content/UX fixes

### Remove module-count badge

`screen-mentorship-form.jsx`, `ProgramPickerCards` — delete the `{p.module_count != null && (...)}` block (lines ~256–267) that renders the "N modules" pill under each program card.

### Mentees-count input

Same file, Step 1 "Number of Mentees" field. Current bug: `onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}` re-coerces on every keystroke, so clearing the field to type a new value snaps back to 20 mid-edit.

Fix: hold the raw string while typing, parse only on blur/step-change, and validate 2–8:
```js
const [maxParticipantsInput, setMaxParticipantsInput] = useState(String(maxParticipants));
// onChange: setMaxParticipantsInput(e.target.value)  — no coercion
// onBlur: const n = parseInt(maxParticipantsInput, 10);
//         if (!Number.isNaN(n)) setMaxParticipants(n);
```
- Inline red hint (same pattern as other `Field` hints) when the parsed value is `<2` or `>8`: "Enter a number between 2 and 8."
- `step1Valid` gains `&& maxParticipants >= 2 && maxParticipants <= 8` so Continue is blocked while out of range.
- Hint text updated from "Recommended minimum: 2. No maximum limit enforced." to "Must be between 2 and 8 mentees."

---

## D. Cold-start branded splash

`ScopeShell.jsx`, the `if (!ready)` branch (currently a centered "Loading…" text) becomes a full-screen component reusing the login screen's visual language:
- `T.gradientHero` background, floating logo mark (same SVG as `screen-login.jsx`), "MNCH Kenya" + "Mentorship Platform" text, animated spinner.
- Purely presentational — `ready` is already computed from the existing `applyScopes` effect; no new data-flow or timing logic.
- Scope tab switches after this point remain instant (unchanged), since scope data is already loading/cached per-component as today.

---

## Testing

- **A:** manual pass — register a test account on a device with the release-signed APK, confirm the email link opens the app (not the browser) via App Links, set password, log in. Fallback path (link opened without app installed) still lands on the existing web pages, unchanged. Forgot-password: same deep-link path, existing account.
- **B:** manual pass on at least one 3-button-nav and one gesture-nav Android device (or Chrome DevTools device toolbar with a large bottom inset simulated) — confirm FAB, Class Report back button, and every fixed footer button are fully visible and tappable.
- **C:** manual — type a single digit into mentees count without it reverting; confirm 1 and 9 both show the inline error and block Continue; 2–8 pass.
- **D:** manual — cold-start the app (fresh login), confirm splash shows then transitions to the tab content.

No automated test suite exists for this app (per project conventions) — all verification is manual.
