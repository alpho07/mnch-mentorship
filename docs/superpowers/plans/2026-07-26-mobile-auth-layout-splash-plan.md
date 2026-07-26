# Mobile App — Auth, Layout Safe-Area, and Cold-Start Splash — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Register + Forgot Password screens for the MNCH mobile app (with Android App Links deep-linking back into the app), fix a confirmed z-index/safe-area bug class affecting FABs, footers, and sheets on Android, fix two small content/UX bugs, and add a branded cold-start splash.

**Architecture:** Backend adds two new unauthenticated Sanctum API endpoints (`register`, `verify-account`) to the existing `AuthController`, mirroring the web `CustomRegister`/`AccountVerificationController` logic exactly (same "pending until email-verified" model). The mobile app gets three new pre-auth screens (Register, Forgot Password, Set Password) plus a Capacitor `appUrlOpen` listener that routes incoming App Link URLs to Set Password. Layout fixes are a mechanical sweep: a new shared z-index scale + safe-area helper in `constants.js`, applied to every fixed-position bottom element.

**Tech Stack:** Laravel 12 + Sanctum (backend), React + Capacitor 8 (mobile), no JS test runner in this app (manual verification per project convention) — backend gets real PHPUnit Feature tests (`composer test` / `php artisan test`).

## Global Constraints

- Mobile app has no automated test runner — every mobile task's verification step is a manual `npm run dev` check, per `public/m-assessment-app/CLAUDE.md`.
- Backend follows the existing `tests/Feature/Api/*Test.php` convention: `RefreshDatabase`, `Tests\TestCase`, Sanctum bearer tokens via `createToken()->plainTextToken`.
- Never hardcode hex colors in mobile screens — use `T.*` tokens from `src/constants.js` (project convention).
- All new fixed-position bottom-anchored elements must use `T.bottomSafe(...)` and the shared `Z` scale from Task 4 — no more ad-hoc `bottom: 80` / raw `zIndex` numbers.
- Register form drops the CAPTCHA present on `admin/register` but otherwise mirrors every field 1:1 (per user decision during brainstorming).

---

## Task 1: Remove module-count badge from program picker cards

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-mentorship-form.jsx:256-267`

**Interfaces:** None — pure JSX deletion, no signature changes.

- [ ] **Step 1: Delete the module-count badge block**

In `ProgramPickerCards`, remove this block (the `{p.module_count != null && (...)}` badge rendered under the program title):

```jsx
                        {/* module count */}
                        {p.module_count != null && (
                            <div style={{
                                marginTop: "auto", paddingTop: 6,
                                fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.04em",
                                color: "rgba(255,255,255,0.85)",
                                background: "rgba(255,255,255,0.18)", border: "1px solid rgba(255,255,255,0.28)",
                                borderRadius: 99, padding: "3px 8px", display: "inline-block",
                            }}>
                                {p.module_count} module{p.module_count !== 1 ? "s" : ""}
                            </div>
                        )}
```

- [ ] **Step 2: Manual verification**

Run `npm run dev` in `public/m-assessment-app`, log in, open Mentorships → New Mentorship → Step 1. Confirm the program cards no longer show a "N modules" pill.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-mentorship-form.jsx
git commit -m "fix(mobile): remove module-count badge from program picker cards"
```

---

## Task 2: Fix mentees-count input editing + add 2–8 validation

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-mentorship-form.jsx`

**Interfaces:**
- Consumes: existing `maxParticipants` state, `Field` component, `inputStyle` (all defined earlier in the same file).
- Produces: new local state `maxParticipantsInput` (string) — not consumed elsewhere.

**Bug:** `onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}` re-coerces every keystroke, so clearing the field to type a new value snaps back to `20` mid-edit, making it impossible to type e.g. a lone "3".

- [ ] **Step 1: Add local string state for the raw input**

Near the other Step-1 state declarations (around line 301, next to `const [maxParticipants, setMaxParticipants] = useState(20);`):

```js
    const [maxParticipants, setMaxParticipants]           = useState(20);
    const [maxParticipantsInput, setMaxParticipantsInput] = useState("20");
```

- [ ] **Step 2: Replace the input's onChange/onBlur and add validation**

Replace the existing "Number of Mentees" `Field` block:

```jsx
                            <Field label="Number of Mentees" hint="Recommended minimum: 2. No maximum limit enforced.">
                                <input
                                    type="number"
                                    value={maxParticipants}
                                    min={1}
                                    onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}
                                    style={inputStyle}
                                />
                            </Field>
```

with:

```jsx
                            <Field
                                label="Number of Mentees"
                                hint={
                                    maxParticipantsInput !== "" && (maxParticipants < 2 || maxParticipants > 8)
                                        ? null
                                        : "Must be between 2 and 8 mentees."
                                }
                            >
                                <input
                                    type="number"
                                    value={maxParticipantsInput}
                                    onChange={e => {
                                        const raw = e.target.value;
                                        setMaxParticipantsInput(raw);
                                        const n = parseInt(raw, 10);
                                        if (!Number.isNaN(n)) setMaxParticipants(n);
                                    }}
                                    onBlur={() => {
                                        const n = parseInt(maxParticipantsInput, 10);
                                        if (Number.isNaN(n)) {
                                            setMaxParticipantsInput(String(maxParticipants));
                                        }
                                    }}
                                    style={inputStyle}
                                />
                                {maxParticipantsInput !== "" && (maxParticipants < 2 || maxParticipants > 8) && (
                                    <div style={{ fontSize: 11, color: "#DC2626", marginTop: 4 }}>
                                        Enter a number between 2 and 8.
                                    </div>
                                )}
                            </Field>
```

- [ ] **Step 3: Gate `step1Valid` on the range**

Find:
```js
    const step1Valid = programId && countyId && facilityId && startDate && endDate;
```
Replace with:
```js
    const step1Valid = programId && countyId && facilityId && startDate && endDate
        && maxParticipants >= 2 && maxParticipants <= 8;
```

- [ ] **Step 4: Manual verification**

`npm run dev`, open New Mentorship Step 1. Clear the mentees field and type "3" — confirm it holds "3" (doesn't snap to 20). Type "1" — confirm the red "Enter a number between 2 and 8" hint appears and Continue is disabled. Type "8" — confirm Continue re-enables. Type "9" — confirm it's blocked again.

- [ ] **Step 5: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-mentorship-form.jsx
git commit -m "fix(mobile): allow editing mentees-count field, validate 2-8 range"
```

---

## Task 3: Branded cold-start splash in ScopeShell

**Files:**
- Modify: `public/m-assessment-app/src/components/ScopeShell.jsx:97-103`

**Interfaces:** None — replaces the `!ready` early-return JSX only; `ready` state/logic is untouched.

- [ ] **Step 1: Replace the plain loading branch**

Replace:
```jsx
    if (!ready) {
        return (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: T.bg }}>
                <div style={{ color: T.textSub, fontSize: 14 }}>Loading…</div>
            </div>
        );
    }
```

with:

```jsx
    if (!ready) {
        return (
            <div style={{
                display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
                height: "100vh", background: T.gradientHero, textAlign: "center", padding: 24,
                fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif",
            }}>
                <style>{`
                    @keyframes shellLogoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
                    @keyframes shellSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
                `}</style>
                <div style={{
                    width: 64, height: 64, borderRadius: 20,
                    background: T.gradientPrimary,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    marginBottom: 20,
                    boxShadow: "0 8px 28px rgba(79,106,245,0.40)",
                    animation: "shellLogoFloat 4s ease-in-out infinite",
                }}>
                    <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
                        <rect x="13" y="2" width="8" height="30" rx="4" fill="white" fillOpacity="0.95"/>
                        <rect x="2" y="13" width="30" height="8" rx="4" fill="white" fillOpacity="0.95"/>
                        <circle cx="17" cy="17" r="5" fill="white"/>
                    </svg>
                </div>
                <div style={{ color: "white", fontSize: 24, fontWeight: 800, letterSpacing: -0.4 }}>
                    MNCH Kenya
                </div>
                <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 14, fontWeight: 600, marginTop: 4 }}>
                    Mentorship Platform
                </div>
                <svg width="28" height="28" viewBox="0 0 24 24" style={{ marginTop: 28, animation: "shellSpin 1s linear infinite" }}>
                    <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3"/>
                    <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round"/>
                </svg>
            </div>
        );
    }
```

- [ ] **Step 2: Manual verification**

`npm run dev`, fully log out (Profile → Log Out) then log back in. Confirm the branded splash (logo, "MNCH Kenya", spinner) shows briefly before the tab content appears, replacing the old plain "Loading…" text.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/components/ScopeShell.jsx
git commit -m "feat(mobile): branded cold-start splash replacing plain loading text"
```

---

## Task 4: Add shared z-index scale + safe-area bottom-offset helper

**Files:**
- Modify: `public/m-assessment-app/src/constants.js`

**Interfaces:**
- Produces: `Z` (named export, object with `navBar`, `fab`, `header`, `sheet`, `toast` numeric keys) and `T.bottomSafe(navHeight = 64)` (function on the existing `T` export, returns a CSS `calc(...)` string). Tasks 5–8 consume both.

- [ ] **Step 1: Add `Z` and `T.bottomSafe` near the top of `constants.js`, after the `T` object is defined**

```js
// ── Shared z-index scale ─────────────────────────────────────────────────────
// Every fixed-position bottom/overlay element in the app must use one of
// these instead of an ad-hoc number, so stacking order is predictable across
// scopes (Assessments/Mentorships/Trainings) and screens.
export const Z = {
    navBar: 40,   // in-app bottom tab bar (per-scope)
    fab:    45,   // floating action buttons — always above their own scope's navBar
    header: 100,  // ScopeShell sticky header (scope tabs)
    sheet:  200,  // full-screen sheets/modals — must beat header when they cover the top
    toast:  300,  // sync toast, topmost transient UI
};

// bottom offset that clears both this app's own fixed bottom nav (navHeight,
// default 64) and the OS gesture/button nav bar (env(safe-area-inset-bottom)),
// which varies by Android device/SDK.
T.bottomSafe = (navHeight = 64) => `calc(${navHeight}px + env(safe-area-inset-bottom, 0px))`;
```

(Place this after whatever line currently closes the `T` object literal — `Z` must be a top-level named export alongside the existing `T` export, and `T.bottomSafe` is assigned as a property after `T` exists, not inside the object literal itself, since it references `T` isn't needed here but keeps the pattern consistent with how `SECTION_META` etc. are attached elsewhere in this file.)

- [ ] **Step 2: Manual verification**

`npm run dev`, open the browser console on any screen, `import` isn't testable from console directly — instead confirm no build/lint errors: run `npm run lint` and confirm it passes (or has no new errors).

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/constants.js
git commit -m "feat(mobile): add shared z-index scale and safe-area bottom-offset helper"
```

---

## Task 5: Apply Z.fab + bottomSafe to the Assessments and Mentorships FABs

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-assessments-list.jsx:204-229`
- Modify: `public/m-assessment-app/src/screens/screen-mentorships-list.jsx:335-344`

**Interfaces:**
- Consumes: `Z.fab`, `T.bottomSafe` from Task 4. Both files already `import { T } from "../constants.js"` — add `Z` to that import.

**Bug fixed:** both FABs are currently `bottom: 80` (no safe-area add-on, so taller OS gesture bars overlap them) and `zIndex: 10` (actually *below* their own bottom nav's `zIndex: 50`).

- [ ] **Step 1: `screen-assessments-list.jsx`** — update the import and the FAB style

Change (line 2):
```js
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT } from "../constants.js";
```
to:
```js
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT, Z } from "../constants.js";
```

Change the FAB's `style` block:
```js
                style={{
                    position: "fixed",
                    bottom: 80,
                    right: 20,
```
to:
```js
                style={{
                    position: "fixed",
                    bottom: T.bottomSafe(80),
                    right: 20,
```
and:
```js
                    zIndex: 10,
```
to:
```js
                    zIndex: Z.fab,
```

- [ ] **Step 2: `screen-mentorships-list.jsx`** — same treatment

Change the import (line 2):
```js
import { T } from "../constants.js";
```
to:
```js
import { T, Z } from "../constants.js";
```

Change:
```jsx
                <button onClick={onNew} style={{
                    position: "fixed", bottom: 80, right: 16, zIndex: 10,
```
to:
```jsx
                <button onClick={onNew} style={{
                    position: "fixed", bottom: T.bottomSafe(80), right: 16, zIndex: Z.fab,
```

- [ ] **Step 3: Manual verification**

`npm run dev`, open Chrome DevTools device toolbar, pick a device with a large bottom inset simulated (or test on a real gesture-nav Android device/emulator). Open Assessments tab and Mentorships tab, confirm both "+" FABs sit fully above the bottom tab bar with visible spacing, not overlapped or clipped.

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-assessments-list.jsx src/screens/screen-mentorships-list.jsx
git commit -m "fix(mobile): FABs use safe-area-aware offset and correct z-index above their nav bar"
```

---

## Task 6: Standardize BottomNav z-index across all three scopes

**Files:**
- Modify: `public/m-assessment-app/src/scopes/AssessmentsScope.jsx`
- Modify: `public/m-assessment-app/src/scopes/TrainingsScope.jsx`
- Modify: `public/m-assessment-app/src/scopes/MentorshipsScope.jsx`

**Interfaces:** Consumes `Z.navBar` from Task 4.

- [ ] **Step 1: Add/update the constants import in each of the three files**

`AssessmentsScope.jsx` (line 3), change:
```js
import { calcGrade } from '../constants.js';
```
to:
```js
import { calcGrade, Z } from '../constants.js';
```

`TrainingsScope.jsx` has no `constants.js` import today — add a new line after line 1 (`import { useState } from 'react';`):
```js
import { Z } from '../constants.js';
```

`MentorshipsScope.jsx` also has no `constants.js` import today (it hardcodes hex colors in `BottomNav`) — add a new line after line 1 (`import { useState, useEffect } from 'react';`):
```js
import { Z } from '../constants.js';
```

- [ ] **Step 2: `AssessmentsScope.jsx`** — replace the nav's `zIndex: 50` with `zIndex: Z.navBar`

The `<nav style={{ ... zIndex: 50, ... }}>` line in `BottomNav` — change `zIndex: 50` to `zIndex: Z.navBar`.

- [ ] **Step 3: `TrainingsScope.jsx`** — same replacement (`zIndex: 50` → `zIndex: Z.navBar`) in its `BottomNav`.

- [ ] **Step 4: `MentorshipsScope.jsx`** — same replacement in its `BottomNav`:

Change:
```js
            display: 'flex', zIndex: 50,
```
to:
```js
            display: 'flex', zIndex: Z.navBar,
```

- [ ] **Step 5: Manual verification**

`npm run dev`, click through Assessments, Mentorships, and Trainings tabs — confirm each bottom nav still renders correctly (no visual change expected, this is a same-value rename except it's now sourced from one place).

- [ ] **Step 6: Commit**

```bash
cd public/m-assessment-app
git add src/scopes/AssessmentsScope.jsx src/scopes/TrainingsScope.jsx src/scopes/MentorshipsScope.jsx
git commit -m "refactor(mobile): source bottom-nav z-index from shared Z scale"
```

---

## Task 7: Fix Class Report and Module Detail sheets painting behind the scope header

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-class-detail.jsx`
- Modify: `public/m-assessment-app/src/screens/screen-module-detail.jsx`

**Interfaces:** Consumes `Z.sheet` from Task 4.

**Bug fixed:** `ClassReportSheet`'s back button is invisible because the sheet is `zIndex: 60`, while `ScopeShell`'s sticky header (the Assessments/Mentorships/Trainings segmented control) is `zIndex: 100` and — because it's a flex item of `ScopeShell`'s root flex container — paints above any sibling-scoped content with a lower z-index, including this fixed-position sheet. Same root cause hits `ModuleDetailScreen`'s sheet (`zIndex: 50`/`51`).

- [ ] **Step 1: `screen-class-detail.jsx`** — import `Z` and bump the sheet's z-index

Update the top import:
```js
import { T } from "../constants.js";
```
to:
```js
import { T, Z } from "../constants.js";
```

In `ClassReportSheet`, change:
```jsx
        <div style={{ position: "fixed", inset: 0, zIndex: 60, background: T.bg, display: "flex", flexDirection: "column" }}>
```
to:
```jsx
        <div style={{ position: "fixed", inset: 0, zIndex: Z.sheet, background: T.bg, display: "flex", flexDirection: "column" }}>
```

- [ ] **Step 2: `screen-module-detail.jsx`** — same treatment

Change the import (line 3):
```js
import { T } from "../constants.js";
```
to:
```js
import { T, Z } from "../constants.js";
```

Change the backdrop:
```jsx
                style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.45)", zIndex: 50 }}
```
to:
```jsx
                style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.45)", zIndex: Z.sheet }}
```

Change the sheet itself:
```jsx
                position: "fixed", left: 0, right: 0, bottom: 0, zIndex: 51,
```
to:
```jsx
                position: "fixed", left: 0, right: 0, bottom: 0, zIndex: Z.sheet + 1,
```

- [ ] **Step 3: Manual verification**

`npm run dev`, open a Mentorship → a Class → "View Report" (or however the Class Report sheet is reached in the current build) — confirm the "Back" button and "Class Report" header are now fully visible above the scope tabs, not clipped. Repeat for a Module Detail sheet (open a class → a module).

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-class-detail.jsx src/screens/screen-module-detail.jsx
git commit -m "fix(mobile): class report and module detail sheets now paint above the scope header"
```

---

## Task 8: Add safe-area padding to the mentorship form's footer buttons

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-mentorship-form.jsx:1183-1229`

**Interfaces:** None — style-only change, matches the `paddingBottom: "calc(Npx + env(safe-area-inset-bottom, 0px))"` pattern already used consistently in `screen-assessment-form.jsx`, `screen-human-resources.jsx`, `screen-health-products.jsx`, and `screen-assessment-detail.jsx` — this file is the one outlier missing it.

- [ ] **Step 1: Edit mode footer** — change:
```jsx
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.borderLight}` }}>
```
to:
```jsx
                <div style={{ padding: "12px 16px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.borderLight}` }}>
```

- [ ] **Step 2: Step-wizard footer (Back/Continue)** — change:
```jsx
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
```
to:
```jsx
                <div style={{ padding: "12px 16px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
```

- [ ] **Step 3: Manual verification**

`npm run dev` with a simulated large bottom safe-area inset (Chrome DevTools device toolbar), walk through New Mentorship steps 1–4 and Edit Mentorship — confirm "Continue"/"Back"/"Save Changes" always have visible clearance above the device's simulated gesture bar.

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-mentorship-form.jsx
git commit -m "fix(mobile): mentorship form footer respects safe-area-inset-bottom"
```

---

## Task 9: Backend — `POST /api/v1/auth/register`

**Files:**
- Create: `app/Http/Requests/Api/RegisterRequest.php`
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `routes/api.php:36-40`
- Test: `tests/Feature/Api/AuthRegisterApiTest.php`

**Interfaces:**
- Produces: `AuthController::register(RegisterRequest $request): JsonResponse`, route name `api.v1.auth.register`, URL `POST /api/v1/auth/register`. Response: `201` with `{"message": "..."}` on success; `422` with Laravel's standard validation-error shape on failure.
- Consumes: `App\Models\User`, `App\Mail\AccountVerificationMail` (both already exist, used unchanged from the web `CustomRegister` flow).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\Subcounty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthRegisterApiTest extends TestCase
{
    use RefreshDatabase;

    // MainCadre, Department, and Subcounty have no factory classes in this
    // repo — create directly instead of Model::factory().
    private function validPayload(array $overrides = []): array
    {
        $county     = County::factory()->create();
        $subcounty  = Subcounty::create(['name' => 'Test Subcounty', 'county_id' => $county->id]);
        $facility   = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        $cadre      = MainCadre::create(['name' => 'Nurse', 'is_active' => true, 'order' => 1]);
        $department = Department::create(['name' => 'Maternity']);

        return array_merge([
            'first_name'    => 'Jane',
            'middle_name'   => '',
            'last_name'     => 'Mwangi',
            'email'         => 'jane.mwangi@example.test',
            'phone'         => '0712345678',
            'cadre_id'      => $cadre->id,
            'department_id' => $department->id,
            'role'          => 'mentee',
            'county_id'     => $county->id,
            'facility_id'   => $facility->id,
        ], $overrides);
    }

    public function test_registers_pending_user_and_sends_verification_email(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->postJson('/api/v1/auth/register', $payload)
             ->assertCreated()
             ->assertJsonStructure(['message']);

        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('mentee'));
        $this->assertTrue($user->counties->contains('id', $payload['county_id']));
        $this->assertTrue($user->facilities->contains('id', $payload['facility_id']));

        Mail::assertSent(\App\Mail\AccountVerificationMail::class);
    }

    public function test_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create();

        $this->postJson('/api/v1/auth/register', $this->validPayload(['email' => $existing->email]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_rejects_invalid_role(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload(['role' => 'super_admin']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['role']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AuthRegisterApiTest`
Expected: FAIL — route `/api/v1/auth/register` doesn't exist yet (404).

- [ ] **Step 3: Create `RegisterRequest`**

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            'middle_name'   => 'nullable|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'phone'         => 'required|string|max:20|unique:users,phone',
            'cadre_id'      => 'required|integer|exists:main_cadres,id',
            'department_id' => 'required|integer|exists:departments,id',
            'role'          => 'required|in:mentee,facility_mentor',
            'county_id'     => 'required|integer|exists:counties,id',
            'facility_id'   => 'required|integer|exists:facilities,id',
        ];
    }
}
```

- [ ] **Step 4: Add `register()` to `AuthController`**

Add these imports at the top of `app/Http/Controllers/Api/AuthController.php` (alongside the existing `use` block):
```php
use App\Http\Requests\Api\RegisterRequest;
use App\Mail\AccountVerificationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
```

Add this method (e.g. directly above `logout()`):
```php
    /**
     * POST /api/v1/auth/register
     *
     * Mirrors App\Livewire\Auth\CustomRegister::register() — creates the
     * account as "pending" with a random unusable password and emails a
     * signed verification link (see verifyAccount()). No token is returned;
     * the account can't log in until the link is used to set a real password.
     */
    public function register(RegisterRequest $request): JsonResponse {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name'    => ucfirst(strtolower(trim($data['first_name']))),
                'middle_name'   => filled($data['middle_name'] ?? null)
                    ? ucfirst(strtolower(trim($data['middle_name'])))
                    : null,
                'last_name'     => ucfirst(strtolower(trim($data['last_name']))),
                'name'          => trim(collect([
                    $data['first_name'],
                    $data['middle_name'] ?? null,
                    $data['last_name'],
                ])->filter()->map(fn ($n) => ucfirst(strtolower(trim($n))))->implode(' ')),
                'email'         => $data['email'],
                'phone'         => $data['phone'],
                'cadre_id'      => $data['cadre_id'],
                'department_id' => $data['department_id'],
                'facility_id'   => $data['facility_id'],
                'password'      => Hash::make(Str::random(32)),
                'status'        => 'pending',
            ]);

            $user->assignRole($data['role']);
            $user->counties()->sync([$data['county_id']]);
            $user->facilities()->sync([$data['facility_id']]);

            return $user;
        });

        $user->load('roles');
        Mail::to($user->email)->send(new AccountVerificationMail($user));

        return response()->json([
            'message' => "Registration successful. Check {$user->email} for a verification link to set your password.",
        ], 201);
    }
```

- [ ] **Step 5: Register the route**

In `routes/api.php`, inside the existing `auth` group (lines 36–40), add the new route after `login`:
```php
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=AuthRegisterApiTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Api/RegisterRequest.php app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/Api/AuthRegisterApiTest.php
git commit -m "feat(api): add POST /auth/register mirroring the admin registration flow"
```

---

## Task 10: Backend — `POST /api/v1/auth/verify-account/{user}`

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/AuthVerifyAccountApiTest.php`

**Interfaces:**
- Produces: `AuthController::verifyAccount(Request $request, User $user): JsonResponse`, route name `api.v1.auth.verify-account`, URL `POST /api/v1/auth/verify-account/{user}`. Body: `{ expires, signature, password, password_confirmation }` (the `expires`/`signature` are lifted verbatim from the query string of the `/account/verify/{user}` link the app received via the App Link deep link — see Task 13). Response on success: `200` with `{ token, token_type, user }` (same shape as `login()`), so the app can go straight into an authenticated session. On invalid/expired signature: `403`. On already-verified account: `409`.
- Consumes: `App\Models\User`, the existing web route name `account.verify.show` (used only to regenerate the canonical signed URL for validation — no change to the web route itself).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthVerifyAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function signedParams(User $user): array
    {
        $url = URL::temporarySignedRoute('account.verify.show', now()->addDays(7), ['user' => $user->id]);
        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query; // ['expires' => ..., 'signature' => ...]
    }

    public function test_verifies_pending_user_and_returns_token(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $params = $this->signedParams($user);

        $response = $this->postJson("/api/v1/auth/verify-account/{$user->id}", array_merge($params, [
            'password'              => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ]));

        $response->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Sup3rSecret', $user->password));
    }

    public function test_rejects_tampered_signature(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $params = $this->signedParams($user);
        $params['signature'] = 'not-a-real-signature';

        $this->postJson("/api/v1/auth/verify-account/{$user->id}", array_merge($params, [
            'password'              => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ]))->assertStatus(403);
    }

    public function test_rejects_weak_password(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $params = $this->signedParams($user);

        $this->postJson("/api/v1/auth/verify-account/{$user->id}", array_merge($params, [
            'password'              => 'lowercase',
            'password_confirmation' => 'lowercase',
        ]))->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AuthVerifyAccountApiTest`
Expected: FAIL — route doesn't exist yet (404).

- [ ] **Step 3: Add `verifyAccount()` to `AuthController`**

Add this import (alongside the others added in Task 9):
```php
use Illuminate\Support\Facades\URL;
```

Add this method (e.g. directly below `register()`):
```php
    /**
     * POST /api/v1/auth/verify-account/{user}
     *
     * Mobile equivalent of AccountVerificationController::update() — validates
     * the same signed URL (expires/signature) emailed by AccountVerificationMail
     * (regenerated here against the "account.verify.show" route so the check is
     * identical to the web flow), sets the user's real password, marks them
     * verified/active, and returns a login token so the app can go straight
     * into the authenticated state.
     */
    public function verifyAccount(Request $request, User $user): JsonResponse {
        $verifyUrl = URL::route('account.verify.show', [
            'user'      => $user->id,
            'expires'   => $request->input('expires'),
            'signature' => $request->input('signature'),
        ]);

        if (! Request::create($verifyUrl, 'GET')->hasValidSignature()) {
            return response()->json(['message' => 'This verification link is invalid or has expired.'], 403);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This account is already verified. Please log in.'], 409);
        }

        $request->validate([
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/', 'regex:/[0-9]/',
            ],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter and one number.',
            'password.min'   => 'Password must be at least 8 characters.',
        ]);

        $user->update([
            'password'          => Hash::make($request->password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status'            => 'active',
        ]);

        $user->load(['facility.subcounty.county', 'roles']);
        $token = $user->createToken('mobile-app', $this->resolveAbilities($user));

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),
        ]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, add to the same `auth` group:
```php
        Route::post('verify-account/{user}', [AuthController::class, 'verifyAccount'])->name('verify-account');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=AuthVerifyAccountApiTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/Api/AuthVerifyAccountApiTest.php
git commit -m "feat(api): add POST /auth/verify-account for mobile deep-link account verification"
```

---

## Task 11: Host `assetlinks.json` for Android App Links verification

**Files:**
- Create: `public/.well-known/assetlinks.json`

**Interfaces:** None — static file served at `https://mnchkenyamentorship.org/.well-known/assetlinks.json`, required by Android to verify the app owns the domain before auto-opening links in-app.

- [ ] **Step 1: Get the release keystore's SHA-256 fingerprint**

Run (adjust keystore path/alias to the project's actual release signing config — check `android/app/build.gradle` for `signingConfigs` if unsure):
```bash
keytool -list -v -keystore <path-to-release-keystore> -alias <release-key-alias>
```
Copy the `SHA256:` fingerprint value (format `AA:BB:CC:...`, keep the colons).

- [ ] **Step 2: Create the file**

```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.mnch.mentorship.app",
    "sha256_cert_fingerprints": [
      "REPLACE_WITH_RELEASE_SHA256_FINGERPRINT"
    ]
  }
}]
```

Replace `REPLACE_WITH_RELEASE_SHA256_FINGERPRINT` with the value from Step 1.

- [ ] **Step 3: Manual verification**

After deploying, run:
```bash
curl -s https://mnchkenyamentorship.org/.well-known/assetlinks.json
```
Expected: the JSON file content, served with `Content-Type: application/json` (Laravel serves static files from `public/` automatically — no route needed). Also run it through Google's [Statement List Generator/verifier](https://developers.google.com/digital-asset-links/tools/generator) tool if available, or `adb shell am start -a android.intent.action.VIEW -d "https://mnchkenyamentorship.org/account/verify/1"` against a debug build once Task 12 is done, to confirm Android resolves the app as a verified handler (`adb shell pm get-app-links com.mnch.mentorship.app`).

- [ ] **Step 4: Commit**

```bash
git add public/.well-known/assetlinks.json
git commit -m "chore: host assetlinks.json for Android App Links verification"
```

---

## Task 12: Android — App Links intent-filters in `AndroidManifest.xml`

**Files:**
- Modify: `public/m-assessment-app/android/app/src/main/AndroidManifest.xml`

**Interfaces:** None — manifest-only change. Depends on Task 11's `assetlinks.json` being reachable for `autoVerify` to succeed at install time.

- [ ] **Step 1: Add the App Links intent-filter to `MainActivity`**

Inside the existing `<activity android:name=".MainActivity" ...>` block, after the existing `<intent-filter>` (the `MAIN`/`LAUNCHER` one), add:
```xml
            <intent-filter android:autoVerify="true">
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data
                    android:scheme="https"
                    android:host="mnchkenyamentorship.org"
                    android:pathPrefix="/account/verify" />
                <data
                    android:scheme="https"
                    android:host="mnchkenyamentorship.org"
                    android:pathPrefix="/admin/set-password" />
            </intent-filter>
```

The full `<activity>` block should now read:
```xml
        <activity
            android:configChanges="orientation|keyboardHidden|keyboard|screenSize|locale|smallestScreenSize|screenLayout|uiMode|navigation|density"
            android:name=".MainActivity"
            android:label="@string/title_activity_main"
            android:theme="@style/AppTheme.NoActionBarLaunch"
            android:launchMode="singleTask"
            android:exported="true">

            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>

            <intent-filter android:autoVerify="true">
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data
                    android:scheme="https"
                    android:host="mnchkenyamentorship.org"
                    android:pathPrefix="/account/verify" />
                <data
                    android:scheme="https"
                    android:host="mnchkenyamentorship.org"
                    android:pathPrefix="/admin/set-password" />
            </intent-filter>

        </activity>
```

(`android:launchMode="singleTask"` is already set, which is required for App Links to route into the already-running app instance rather than spawning a duplicate.)

- [ ] **Step 2: Rebuild and manual verification**

```bash
cd public/m-assessment-app
npm run build
npx cap sync android
```
Then, with a debug build installed on a device/emulator (after Task 11's `assetlinks.json` is live):
```bash
adb shell am start -a android.intent.action.VIEW -d "https://mnchkenyamentorship.org/account/verify/1?expires=9999999999&signature=test"
```
Expected: the MNCH Mentorship app opens directly (not the browser) — confirms the intent-filter matches. Full signature validation is exercised by Task 10's backend tests and Task 13's app-side handling.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add android/app/src/main/AndroidManifest.xml
git commit -m "feat(android): add App Links intent-filter for account-verify and password-reset URLs"
```

---

## Task 13: Mobile — `appUrlOpen` listener routing deep links to Set Password

**Files:**
- Modify: `public/m-assessment-app/package.json` (add `@capacitor/app` dependency)
- Modify: `public/m-assessment-app/src/App.jsx`

**Interfaces:**
- Produces: `App.jsx` local state `deepLinkTarget` (`{ type: 'verify-account', userId, expires, signature } | { type: 'reset-password', token, email } | null`), passed as a prop to `LoginScreen`/`screen-set-password.jsx` (wired fully in Task 19).
- Consumes: `@capacitor/app`'s `App.addListener('appUrlOpen', ...)`.

- [ ] **Step 1: Install the plugin**

```bash
cd public/m-assessment-app
npm install @capacitor/app
npx cap sync android
```

- [ ] **Step 2: Add the listener and URL parser to `App.jsx`**

Add imports at the top:
```js
import { App as CapacitorApp } from "@capacitor/app";
```

Add this function above the `App` component (after `normaliseUser`):
```js
// ── parseDeepLink ────────────────────────────────────────────────────────────
// Parses the App-Links URLs handed to the app via appUrlOpen:
//   https://mnchkenyamentorship.org/account/verify/{user}?expires=...&signature=...
//   https://mnchkenyamentorship.org/admin/set-password/{token}?email=...
function parseDeepLink(urlString) {
    let url;
    try { url = new URL(urlString); } catch { return null; }

    const verifyMatch = url.pathname.match(/\/account\/verify\/(\d+)/);
    if (verifyMatch) {
        return {
            type: "verify-account",
            userId: verifyMatch[1],
            expires: url.searchParams.get("expires"),
            signature: url.searchParams.get("signature"),
        };
    }

    const resetMatch = url.pathname.match(/\/admin\/set-password\/([^/?]+)/);
    if (resetMatch) {
        return {
            type: "reset-password",
            token: resetMatch[1],
            email: url.searchParams.get("email"),
        };
    }

    return null;
}
```

Inside the `App` component, add state and the listener effect:
```js
    const [deepLinkTarget, setDeepLinkTarget] = useState(null);

    useEffect(() => {
        const listenerPromise = CapacitorApp.addListener("appUrlOpen", ({ url }) => {
            const parsed = parseDeepLink(url);
            if (parsed) setDeepLinkTarget(parsed);
        });
        return () => { listenerPromise.then(l => l.remove()); };
    }, []);
```

- [ ] **Step 3: Manual verification**

`npx cap sync android`, run the app on a device/emulator via Android Studio, then trigger `adb shell am start -a android.intent.action.VIEW -d "https://mnchkenyamentorship.org/account/verify/1?expires=9999999999&signature=test"`. Add a temporary `console.log(deepLinkTarget)` (or React DevTools) to confirm `deepLinkTarget` populates with `{ type: 'verify-account', userId: '1', expires: '9999999999', signature: 'test' }`. Remove the temporary log before committing.

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add package.json package-lock.json src/App.jsx android/app/src/main/java 2>/dev/null
git add -A
git commit -m "feat(mobile): parse App Links deep links via @capacitor/app appUrlOpen listener"
```

---

## Task 14: Mobile — `api.service.js` register/verifyAccount methods

**Files:**
- Modify: `public/m-assessment-app/src/services/api.service.js`

**Interfaces:**
- Produces on `_rawApi.auth`: `register(payload)`, `verifyAccount(userId, expires, signature, password, password_confirmation)`.
- Produces on wrapped `api.auth`: same two methods, `verifyAccount` additionally caches the returned user via `offlineStore.saveUser()` and sets the token (mirroring `login()`'s behavior) so the app can go straight into `ScopeShell` after verification.

- [ ] **Step 1: Add to `_rawApi.auth`** (after the existing `resetPassword` line, around line 268):

```js
        register: (payload) => post('/auth/register', payload),
        verifyAccount: (userId, expires, signature, password, password_confirmation) =>
            post(`/auth/verify-account/${userId}`, { expires, signature, password, password_confirmation }),
```

- [ ] **Step 2: Add to the wrapped `api.auth`** (after the existing `resetPassword: _rawApi.auth.resetPassword,` line, around line 462):

```js
        register: _rawApi.auth.register,
        verifyAccount: async (userId, expires, signature, password, password_confirmation) => {
            const data = await _rawApi.auth.verifyAccount(userId, expires, signature, password, password_confirmation);
            if (data?.token) TokenStore.set(data.token);
            if (data?.user) await offlineStore.saveUser(data.user);
            return data;
        },
```

- [ ] **Step 3: Manual verification**

`npm run dev`, open the browser console, run:
```js
import('/src/services/api.service.js').then(m => console.log(typeof m.default.auth.register, typeof m.default.auth.verifyAccount));
```
Expected: `"function" "function"`. (This is a smoke check only — full behavior is exercised by Tasks 17/16's screens.)

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add src/services/api.service.js
git commit -m "feat(mobile): add register/verifyAccount methods to api.service.js"
```

---

## Task 15: Mobile — export shared form primitives from `screen-mentorship-form.jsx`

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-mentorship-form.jsx`

**Interfaces:**
- Produces (newly exported, previously module-private): `Field`, `SearchableDropdown`, `inputStyle`, `selectStyle` — consumed by `screen-register.jsx` (Task 17) to avoid duplicating ~150 lines of dropdown/field markup.

- [ ] **Step 1: Add `export` to the four declarations**

Change:
```js
const inputStyle = {
```
to:
```js
export const inputStyle = {
```

Change:
```js
const selectStyle = {
```
to:
```js
export const selectStyle = {
```

Change:
```js
function Field({ label, required, hint, children }) {
```
to:
```js
export function Field({ label, required, hint, children }) {
```

Change:
```js
function SearchableDropdown({
```
to:
```js
export function SearchableDropdown({
```

- [ ] **Step 2: Manual verification**

`npm run dev`, confirm the New Mentorship form still renders and works exactly as before (this step only adds `export` keywords — no behavior change). Run `npm run lint` to confirm no new warnings.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-mentorship-form.jsx
git commit -m "refactor(mobile): export Field/SearchableDropdown/inputStyle/selectStyle for reuse"
```

---

## Task 16: Mobile — `screen-set-password.jsx` (shared verify/reset screen)

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-set-password.jsx`

**Interfaces:**
- Produces: `SetPasswordScreen({ mode, target, onDone, onBack })` where `mode` is `"verify-account"` or `"reset-password"`, `target` is the matching `deepLinkTarget` shape from Task 13 (`{ userId, expires, signature }` or `{ token, email }`), `onDone(user)` is called after a successful `verifyAccount`/`resetPassword` call (verify-account also logs the user in immediately since it returns a token; reset-password does not, so `onDone` is called with `null` and the caller should route back to Login).
- Consumes: `api.auth.verifyAccount`, `api.auth.resetPassword` (Task 14).

- [ ] **Step 1: Create the component**

```jsx
import { useState } from "react";
import { T } from "../constants.js";
import { Field, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

export function SetPasswordScreen({ mode, target, onDone, onBack }) {
    const [password, setPassword]         = useState("");
    const [confirmPassword, setConfirmPw] = useState("");
    const [error, setError]               = useState("");
    const [saving, setSaving]             = useState(false);

    const isVerify = mode === "verify-account";

    const handleSubmit = async () => {
        setError("");
        if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
        if (isVerify && (!/[A-Z]/.test(password) || !/[0-9]/.test(password))) {
            setError("Password must contain at least one uppercase letter and one number.");
            return;
        }
        if (password !== confirmPassword) { setError("Passwords do not match."); return; }

        setSaving(true);
        try {
            if (isVerify) {
                const data = await api.auth.verifyAccount(
                    target.userId, target.expires, target.signature, password, confirmPassword
                );
                onDone(data?.user ?? null);
            } else {
                await api.auth.resetPassword(target.token, target.email, password, confirmPassword);
                onDone(null);
            }
        } catch (e) {
            setError(e.message || "Something went wrong. The link may have expired — please request a new one.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "44px 28px 32px", borderRadius: "0 0 28px 28px", margin: "0 6px" }}>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>
                    {isVerify ? "Set Your Password" : "Choose a New Password"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 14, marginTop: 4 }}>
                    {isVerify
                        ? "Welcome to MNCH Kenya — create a password to activate your account."
                        : "Enter a new password for your account."}
                </div>
            </div>

            <div style={{ flex: 1, padding: "28px 20px", overflowY: "auto" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 18, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}

                <Field label="New Password" required hint={isVerify ? "At least 8 characters, one uppercase letter, one number." : "At least 8 characters."}>
                    <input type="password" value={password} onChange={e => setPassword(e.target.value)} style={inputStyle} placeholder="Enter new password" />
                </Field>
                <Field label="Confirm Password" required>
                    <input type="password" value={confirmPassword} onChange={e => setConfirmPw(e.target.value)} style={inputStyle} placeholder="Re-enter new password" />
                </Field>

                <button onClick={handleSubmit} disabled={saving} style={{
                    width: "100%", padding: 15, borderRadius: T.radiusSm, border: "none",
                    background: saving ? T.borderLight : T.gradientPrimary,
                    color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: saving ? "not-allowed" : "pointer", marginTop: 8,
                }}>
                    {saving ? "Saving…" : "Set Password"}
                </button>

                {onBack && (
                    <button type="button" onClick={onBack} style={{
                        background: "none", border: "none", padding: 0, textAlign: "center",
                        marginTop: 16, fontSize: 13, color: T.primary, fontWeight: 600,
                        cursor: "pointer", width: "100%",
                    }}>
                        Back to Login
                    </button>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Manual verification**

Deferred to Task 19 (this screen has no entry point until it's wired into `App.jsx`'s deep-link routing) — confirmed there.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-set-password.jsx
git commit -m "feat(mobile): add shared SetPasswordScreen for account-verify and password-reset deep links"
```

---

## Task 17: Mobile — `screen-register.jsx`

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-register.jsx`

**Interfaces:**
- Produces: `RegisterScreen({ onRegistered, onBack })` — `onRegistered()` is called after a successful `api.auth.register(...)` call (no user/token — the caller should show/stay on a confirmation state or route back to Login).
- Consumes: `api.auth.register` (Task 14), `api.lookups.programs/counties/cadres/departments/facilitiesByCounty` (all pre-existing, same calls `screen-mentorship-form.jsx` already makes), `Field`/`SearchableDropdown`/`inputStyle` (Task 15).

- [ ] **Step 1: Create the component**

```jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { Field, SearchableDropdown, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

const ROLE_OPTIONS = [
    { id: "mentee", name: "Mentee" },
    { id: "facility_mentor", name: "Facility Mentor" },
];

export function RegisterScreen({ onRegistered, onBack }) {
    const [firstName, setFirstName]   = useState("");
    const [middleName, setMiddleName] = useState("");
    const [lastName, setLastName]     = useState("");
    const [email, setEmail]           = useState("");
    const [phone, setPhone]           = useState("");
    const [cadreId, setCadreId]       = useState("");
    const [departmentId, setDeptId]   = useState("");
    const [role, setRole]             = useState("mentee");
    const [countyId, setCountyId]     = useState("");
    const [facilityId, setFacilityId] = useState("");

    const [cadres, setCadres]         = useState([]);
    const [departments, setDeptments] = useState([]);
    const [counties, setCounties]     = useState([]);
    const [facilities, setFacilities] = useState([]);
    const [facilitiesLoading, setFacilitiesLoading] = useState(false);

    const [error, setError]     = useState("");
    const [saving, setSaving]   = useState(false);
    const [done, setDone]       = useState(false);

    useEffect(() => {
        api.lookups.cadres().then(d => setCadres(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.lookups.departments().then(d => setDeptments(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.lookups.counties().then(d => setCounties(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (!countyId) { setFacilities([]); setFacilityId(""); return; }
        setFacilitiesLoading(true);
        api.lookups.facilitiesByCounty(countyId)
            .then(list => {
                const arr = (Array.isArray(list?.data) ? list.data : Array.isArray(list) ? list : [])
                    .map(f => ({ ...f, label: f?.label || (f?.mfl_code ? `${f.mfl_code} - ${f.name}` : f?.name) }))
                    .filter(f => f.id && f.name);
                setFacilities(arr);
                setFacilityId("");
            })
            .catch(() => setFacilities([]))
            .finally(() => setFacilitiesLoading(false));
    }, [countyId]);

    const valid = firstName.trim() && lastName.trim() && email.trim() && phone.trim()
        && cadreId && departmentId && role && countyId && facilityId;

    const handleSubmit = async () => {
        setError("");
        if (!valid) { setError("Please fill in all required fields."); return; }
        setSaving(true);
        try {
            await api.auth.register({
                first_name: firstName.trim(),
                middle_name: middleName.trim() || null,
                last_name: lastName.trim(),
                email: email.trim(),
                phone: phone.trim(),
                cadre_id: parseInt(cadreId),
                department_id: parseInt(departmentId),
                role,
                county_id: parseInt(countyId),
                facility_id: parseInt(facilityId),
            });
            setDone(true);
        } catch (e) {
            setError(e.message || "Registration failed. Please check your details and try again.");
        } finally {
            setSaving(false);
        }
    };

    if (done) {
        return (
            <div style={{ display: "flex", flexDirection: "column", height: "100%", alignItems: "center",
                justifyContent: "center", padding: 32, textAlign: "center", background: T.bg }}>
                <div style={{ fontSize: 40, marginBottom: 12 }}>📬</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: T.text, marginBottom: 8 }}>Check your email</div>
                <div style={{ fontSize: 14, color: T.textSub, marginBottom: 28, lineHeight: 1.6 }}>
                    We've sent a verification link to <strong>{email.trim()}</strong>. Open it on this device to set your password and activate your account.
                </div>
                <button onClick={onRegistered} style={{
                    padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none",
                    borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer",
                }}>
                    Back to Login
                </button>
            </div>
        );
    }

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "40px 20px 20px", borderRadius: "0 0 28px 28px", margin: "0 6px" }}>
                <button onClick={onBack} style={{ background: "rgba(255,255,255,0.15)", border: "none", cursor: "pointer",
                    padding: "6px 10px", borderRadius: 10, marginBottom: 12, color: "white", fontSize: 12, fontWeight: 600 }}>
                    ← Back
                </button>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>Create Account</div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 4 }}>Join the MNCH Mentorship Platform</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: "20px 20px 0" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 16, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}

                <Field label="First Name" required><input value={firstName} onChange={e => setFirstName(e.target.value)} style={inputStyle} /></Field>
                <Field label="Middle Name"><input value={middleName} onChange={e => setMiddleName(e.target.value)} style={inputStyle} /></Field>
                <Field label="Last Name" required><input value={lastName} onChange={e => setLastName(e.target.value)} style={inputStyle} /></Field>
                <Field label="Email Address" required><input type="email" value={email} onChange={e => setEmail(e.target.value)} style={inputStyle} /></Field>
                <Field label="Phone Number" required><input type="tel" value={phone} onChange={e => setPhone(e.target.value)} style={inputStyle} /></Field>

                <Field label="Cadre" required>
                    <SearchableDropdown options={cadres} value={cadreId} onChange={setCadreId} placeholder="Select cadre..." searchPlaceholder="Search cadre..." />
                </Field>
                <Field label="Department" required>
                    <SearchableDropdown options={departments} value={departmentId} onChange={setDeptId} placeholder="Select department..." searchPlaceholder="Search department..." />
                </Field>
                <Field label="Role" required>
                    <SearchableDropdown options={ROLE_OPTIONS} value={role} onChange={setRole} placeholder="Select role..." />
                </Field>
                <Field label="County" required hint="Select county to load facilities">
                    <SearchableDropdown options={counties} value={countyId} onChange={setCountyId} placeholder="Select county..." searchPlaceholder="Search county..." />
                </Field>
                <Field label="Facility" required hint={!countyId ? "Select a county first" : facilitiesLoading ? "Loading facilities…" : undefined}>
                    <SearchableDropdown
                        options={facilities} value={facilityId} onChange={setFacilityId}
                        disabled={!countyId || facilitiesLoading}
                        getLabel={f => f.label ?? f.name}
                        placeholder={facilitiesLoading ? "Loading facilities..." : "Select facility..."}
                        searchPlaceholder="Search facility or MFL..."
                    />
                </Field>
            </div>

            <div style={{ padding: "12px 20px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.borderLight}` }}>
                <button onClick={handleSubmit} disabled={saving || !valid} style={{
                    width: "100%", padding: 14, borderRadius: T.radiusSm, border: "none",
                    background: (saving || !valid) ? T.borderLight : T.gradientPrimary,
                    color: (saving || !valid) ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: (saving || !valid) ? "not-allowed" : "pointer",
                }}>
                    {saving ? "Registering…" : "Register"}
                </button>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Manual verification**

Deferred to Task 19 (no entry point until wired into `App.jsx`) — confirmed there.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-register.jsx
git commit -m "feat(mobile): add RegisterScreen mirroring admin/register field set"
```

---

## Task 18: Mobile — `screen-forgot-password.jsx`

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-forgot-password.jsx`

**Interfaces:**
- Produces: `ForgotPasswordScreen({ onBack })` — internal only, no callback needed on success (shows a "check your email" confirmation state in place, same pattern as `RegisterScreen`).
- Consumes: `api.auth.forgotPassword` (pre-existing).

- [ ] **Step 1: Create the component**

```jsx
import { useState } from "react";
import { T } from "../constants.js";
import { Field, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

export function ForgotPasswordScreen({ onBack }) {
    const [email, setEmail]   = useState("");
    const [error, setError]   = useState("");
    const [saving, setSaving] = useState(false);
    const [sent, setSent]     = useState(false);

    const handleSubmit = async () => {
        setError("");
        if (!email.trim()) { setError("Enter your email address."); return; }
        setSaving(true);
        try {
            await api.auth.forgotPassword(email.trim());
            setSent(true);
        } catch (e) {
            setError(e.message || "Could not send reset link. Check the email address and try again.");
        } finally {
            setSaving(false);
        }
    };

    if (sent) {
        return (
            <div style={{ display: "flex", flexDirection: "column", height: "100%", alignItems: "center",
                justifyContent: "center", padding: 32, textAlign: "center", background: T.bg }}>
                <div style={{ fontSize: 40, marginBottom: 12 }}>📬</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: T.text, marginBottom: 8 }}>Check your email</div>
                <div style={{ fontSize: 14, color: T.textSub, marginBottom: 28, lineHeight: 1.6 }}>
                    We've sent a password reset link to <strong>{email.trim()}</strong>. Open it on this device to set a new password.
                </div>
                <button onClick={onBack} style={{
                    padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none",
                    borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer",
                }}>
                    Back to Login
                </button>
            </div>
        );
    }

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "40px 20px 20px", borderRadius: "0 0 28px 28px", margin: "0 6px" }}>
                <button onClick={onBack} style={{ background: "rgba(255,255,255,0.15)", border: "none", cursor: "pointer",
                    padding: "6px 10px", borderRadius: 10, marginBottom: 12, color: "white", fontSize: 12, fontWeight: 600 }}>
                    ← Back
                </button>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>Forgot Password</div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 4 }}>
                    Enter your email and we'll send you a reset link.
                </div>
            </div>

            <div style={{ flex: 1, padding: "28px 20px", overflowY: "auto" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 18, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}
                <Field label="Email Address" required>
                    <input type="email" value={email} onChange={e => setEmail(e.target.value)}
                        onKeyDown={e => e.key === "Enter" && handleSubmit()} style={inputStyle} placeholder="Enter your email" />
                </Field>
                <button onClick={handleSubmit} disabled={saving} style={{
                    width: "100%", padding: 15, borderRadius: T.radiusSm, border: "none",
                    background: saving ? T.borderLight : T.gradientPrimary,
                    color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: saving ? "not-allowed" : "pointer", marginTop: 8,
                }}>
                    {saving ? "Sending…" : "Send Reset Link"}
                </button>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Manual verification**

Deferred to Task 19 (no entry point until wired into `App.jsx`) — confirmed there.

- [ ] **Step 3: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-forgot-password.jsx
git commit -m "feat(mobile): add ForgotPasswordScreen"
```

---

## Task 19: Mobile — wire Register/Forgot Password/deep links into `App.jsx` and `screen-login.jsx`

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-login.jsx`
- Modify: `public/m-assessment-app/src/App.jsx`

**Interfaces:**
- Consumes: `RegisterScreen`, `ForgotPasswordScreen`, `SetPasswordScreen` (Tasks 16–18), `deepLinkTarget`/`parseDeepLink` (Task 13).
- Produces: `LoginScreen` gains two new props, `onGoToRegister` and `onGoToForgotPassword` (both optional callbacks — buttons only render their new behavior when provided, so this is backward compatible).

- [ ] **Step 1: Wire the two dead-end buttons in `screen-login.jsx`**

Add a new prop to the component signature:
```js
export function LoginScreen({ onLogin, onGoToRegister, onGoToForgotPassword }) {
```

Replace the existing non-functional "Forgot password?" button:
```jsx
                <button type="button" style={{
                    background:"none", border:"none", padding:0,
                    textAlign:"center", marginTop:16, fontSize:13, color:T.primary, fontWeight:600,
                    cursor:"pointer", width:"100%",
                }}>
                    Forgot password?
                </button>
```
with:
```jsx
                <button type="button" onClick={onGoToForgotPassword} style={{
                    background:"none", border:"none", padding:0,
                    textAlign:"center", marginTop:16, fontSize:13, color:T.primary, fontWeight:600,
                    cursor:"pointer", width:"100%",
                }}>
                    Forgot password?
                </button>

                <button type="button" onClick={onGoToRegister} style={{
                    background:"none", border:"none", padding:0,
                    textAlign:"center", marginTop:12, fontSize:13, color:T.textMuted, fontWeight:500,
                    cursor:"pointer", width:"100%",
                }}>
                    Don't have an account? <span style={{ color: T.primary, fontWeight: 700 }}>Register</span>
                </button>
```

- [ ] **Step 2: Add pre-auth screen routing to `App.jsx`**

Add imports:
```js
import { RegisterScreen } from "./screens/screen-register.jsx";
import { ForgotPasswordScreen } from "./screens/screen-forgot-password.jsx";
import { SetPasswordScreen } from "./screens/screen-set-password.jsx";
```

Add state (alongside `deepLinkTarget` from Task 13):
```js
    const [authScreen, setAuthScreen] = useState("login"); // "login" | "register" | "forgot-password"
```

Replace the existing pre-auth branch:
```jsx
    if (!user) {
        return <LoginScreen onLogin={(u) => setUser(normaliseUser(u))} />;
    }
```
with:
```jsx
    if (!user) {
        if (deepLinkTarget?.type === "verify-account") {
            return (
                <SetPasswordScreen
                    mode="verify-account"
                    target={deepLinkTarget}
                    onDone={(verifiedUser) => {
                        setDeepLinkTarget(null);
                        if (verifiedUser) setUser(normaliseUser(verifiedUser));
                        else setAuthScreen("login");
                    }}
                />
            );
        }
        if (deepLinkTarget?.type === "reset-password") {
            return (
                <SetPasswordScreen
                    mode="reset-password"
                    target={deepLinkTarget}
                    onDone={() => { setDeepLinkTarget(null); setAuthScreen("login"); }}
                    onBack={() => { setDeepLinkTarget(null); setAuthScreen("login"); }}
                />
            );
        }
        if (authScreen === "register") {
            return <RegisterScreen onRegistered={() => setAuthScreen("login")} onBack={() => setAuthScreen("login")} />;
        }
        if (authScreen === "forgot-password") {
            return <ForgotPasswordScreen onBack={() => setAuthScreen("login")} />;
        }
        return (
            <LoginScreen
                onLogin={(u) => setUser(normaliseUser(u))}
                onGoToRegister={() => setAuthScreen("register")}
                onGoToForgotPassword={() => setAuthScreen("forgot-password")}
            />
        );
    }
```

- [ ] **Step 3: Manual verification**

`npm run dev`:
1. From Login, tap "Don't have an account? Register" — confirm `RegisterScreen` opens, "← Back" returns to Login.
2. Tap "Forgot password?" — confirm `ForgotPasswordScreen` opens, "← Back" returns to Login.
3. Fill out Register with a fresh email and submit — confirm the "Check your email" confirmation screen appears, and (checking the Laravel log/mail driver in the backend) that `AccountVerificationMail` was queued/sent.
4. Submit Forgot Password with an existing account's email — confirm the "Check your email" confirmation appears.
5. On an Android device/emulator with the App Link wired (Tasks 11–13), tap the emailed verification link — confirm the app opens directly to `SetPasswordScreen` in verify-account mode, and submitting a valid password logs the user straight into `ScopeShell`.

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add src/screens/screen-login.jsx src/App.jsx
git commit -m "feat(mobile): wire Register/Forgot Password screens and deep-link routing into App.jsx"
```

---

## Notes for the executing engineer

- Tasks 1–8 have no interdependencies on 9–19 and can be done first as a quick, low-risk batch.
- Tasks 9–10 (backend) can be done in parallel with 1–8, but must land before Task 14 (mobile API client) since 14 calls the routes they create.
- Task 15 (exports) must land before Tasks 17/18/16 (screens that import from `screen-mentorship-form.jsx`).
- Tasks 11–12 (App Links infra) are infrastructure/deploy steps with no code dependency on the others, but Task 19's end-to-end verification (step 5) needs them live to fully test the deep-link path — everything else in Task 19 is testable without them.
- This app has no automated JS test suite (per project convention) — every mobile-side "test" step in this plan is a manual `npm run dev` check, not a code-based assertion.
