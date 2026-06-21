# Maternal Health Mentorship — Implementation Plan

> Status: Design decisions finalized. Ready for backend implementation.
> Created: 2026-06-15
> Updated: 2026-06-21
> Context: Adding a Maternal Health (EmONC) mentorship program to the existing MNCH platform without disrupting Infant Care, Newborn Care, and Child Care programs.

---

## 1. What was explored

The following core models, Filament resources, migrations, controllers, views, and seeders were reviewed to understand the current mentorship architecture:

### Key models
- `Program` / `ProgramModule` / `ModuleSession` — curriculum templates
- `Training` — discriminator `type = 'facility_mentorship'` for mentorships
- `MentorshipClass` — class/cohort
- `ClassModule` — program module assigned to a class
- `ClassSession` — scheduled session within a class module
- `ClassParticipant` / `MenteeModuleProgress` — enrollment and progress
- `ClassAttendance` / `SessionAttendance` — attendance records
- `ModuleAssessment` / `ModuleAssessmentResult` — existing assessments (not reused for EmONC)
- `User` — roles and geographic scoping

### Key Filament resources / pages
- `MentorshipTrainingResource` — mentorship CRUD + program picker
- `ManageMentorshipClasses` — class/cohort creation and module assignment
- `ManageClassModules` — add/remove/start/complete modules
- `ManageClassMentees` — enroll mentees, send invites, start/end class
- `ManageModuleSessions` — sessions inside a module
- `ManageModuleAssessments` — module assessments and results
- `ProgramResource` / `ProgramModuleResource` — curriculum admin

### Key controllers / routes
- `MenteeEnrollmentController` — public `/enroll/{token}` flow
- `MenteeClassProgressController` — authenticated `/my-class/{class}` dashboard
- `ModuleAttendanceController` — public `/module/attend/{token}` attendance
- Web routes in `routes/web.php`

### Seeders
- `RolePermissionSeeder` — existing roles
- `ProgramModulesSeeder` — Infant/Child and Newborn program modules

---

## 2. Current architecture (reusable foundation)

```
Program
  └── ProgramModule
        └── ModuleSession (template)

Training (facility_mentorship)
  └── MentorshipClass (cohort)
        └── ClassModule → ProgramModule
              └── ClassSession
              └── ClassAttendance
              └── ModuleAssessment
        └── ClassParticipant (mentee enrollment)
              └── MenteeModuleProgress
              └── ModuleAssessmentResult
```

This architecture already supports:
- Program-based curriculum
- Cohort management
- Module-level attendance
- Assessment scoring
- Public enrollment via token
- Geographic scoping (county → subcounty → facility)
- Role-based access

---

## 3. EmONC logbook findings

The reference document (`EmONC SKILLS LOGBOOK`) defines:

- **13 modules:**
  1. Ante Partum Hemorrhage
  2. Partograph Use and Interpretation
  3. Obstructed Labour
  4. Active Management of the Third Stage of Labor (AMSTL)
  5. Management of Postpartum Hemorrhage (PPH)
  6. Management of Cord Prolapse
  7. Vaginal Breech Delivery
  8. Shoulder Dystocia Delivery
  9. Vaginal Vacuum Assisted Delivery
  10. Maternal Resuscitation
  11. Management of Maternal Shock
  12. Management of Pre-Eclampsia/Eclampsia
  13. Immediate Neonatal Resuscitation

- **Module 5 contains 10 tracks (sub-skills):**
  1. Bimanual compression of the uterus
  2. Compression of abdominal aorta
  3. Removal of retained placenta
  4. Uterine inversion
  5a. The placement of the intrauterine balloon tamponade (condom tamponade)
  5b. The placement of the intrauterine balloon tamponade (free flow system)
  6. Repair of perineal tear
  7. Repair of cervical tear
  8. The placement of the B-Lynch suture
  9. Placement of non-pneumatic anti-shock garment (NASG)
  10. Post-partum hemorrhage simulation

- **Pass mark:** 85% per skill/module/track.
- **Certification flow:**
  1. Mentor selects and orients mentee
  2. Training and mentorship by mentor
  3. Assessment by mentor
  4. Mentee scores ≥ 85% for each skill
  5. Mentee completes all topics/skills
  6. Mentor approves mentee for certification
  7. Certification by DRMH (Head DRMH)
- **Mentorship timeline:** 6 months closure; recommended weekly 6 hours.
- **Module/track logbook page includes:** start date, end date, score, pass/fail (≥85%), additional comments, mentor signature.

---

## 4. Proposed design

### 4.1 Track handling — self-referential `ProgramModule`

Instead of creating a separate `ProgramModuleTrack` table, tracks will be stored as child `ProgramModule` records using a nullable `parent_id`.

```
Program: Maternal Health (EmONC)
  └── ProgramModule 1..13
        └── ProgramModule child records (tracks, when applicable)

Example:
ProgramModule: Management of Postpartum Hemorrhage (PPH)
  ├── ProgramModule: Bimanual compression of the uterus
  ├── ProgramModule: Compression of abdominal aorta
  ├── ProgramModule: Removal of retained placenta
  ├── ProgramModule: Uterine inversion
  ├── ProgramModule: The placement of the intrauterine balloon tamponade (condom tamponade)
  ├── ProgramModule: The placement of the intrauterine balloon tamponade (free flow system)
  ├── ProgramModule: Repair of perineal tear
  ├── ProgramModule: Repair of cervical tear
  ├── ProgramModule: The placement of the B-Lynch suture
  ├── ProgramModule: Placement of non-pneumatic anti-shock garment (NASG)
  └── ProgramModule: Post-partum hemorrhage simulation
```

This means:
- A `ProgramModule` with `parent_id = null` is a **module**.
- A `ProgramModule` with `parent_id` set is a **track**.
- When a module has tracks, the tracks become the actual class modules; the parent module acts as a grouping/header only.
- This reuses the existing mentorship architecture without changing the `ClassModule` → attendance/progress flow.

### 4.2 Program structure

```
Program: Maternal Health (EmONC)
  └── ProgramModule 1..13
        └── ProgramModule (tracks, only Module 5 in EmONC)
        └── ProgramModuleActivity (attachable via checkboxes): CME, Hands-on Demo, Drill
        └── ProgramModuleContent: intro notes, videos, case scenarios
        └── ProgramModuleQuiz: pre-test and post-test question banks
```

### 4.3 New tables/models

| Table / Model | Purpose |
|---------------|---------|
| `program_module_activities` | Pivot: configurable activity attachment to modules/tracks |
| `activities` | Master list of activity types: CME, Hands-on Demo, Drill |
| `program_module_contents` | Curated learning items: intro notes, video links, case scenarios |
| `program_module_quizzes` | Quiz header per module/track with `type` pre/post/both |
| `quiz_questions` | Multiple-choice questions |
| `quiz_options` | Answer options per question with `is_correct` flag |
| `quiz_attempts` | Pre-test / post-test attempts and scores |
| `quiz_responses` | Individual answers per attempt |
| `mentee_uploads` | Hands-on video uploads by mentees (future phase) |
| `module_rubrics` / `mentee_rubric_scores` | Configurable rubric criteria + mentor scores (future phase) |

### 4.4 Changes to existing tables

- `program_modules` — add `parent_id` (nullable, self-referencing foreign key).
- `program_modules` — add `type` enum or derive from `parent_id` presence (module vs track).
- `program_modules` — `start_date` / `end_date` added for curriculum-level module scheduling (used by non-EmONC programs; not surfaced on `class_modules` for EmONC).
- `role_permissions` / `roles` — add `newbie` role.

### 4.5 Mentor flow (future phase)

1. Create mentorship:
   - Select County → Facility
   - Select Program (Maternal Health)
   - Set number of mentees
2. Create class/cohort:
   - Name of class/cohort
   - Description of identified gap
3. Select mentees/newbies
4. Select modules (with start/end dates):
   - Activities (CME, Hands-on Demo, Drill) auto-attach based on curriculum config
   - Tracks auto-expand into child class modules where applicable
5. Send invite link to mentees
6. Mark activity attendance and completion
7. Score hands-on video uploads using rubric
8. Approve certificate

### 4.6 Mentee/newbie flow (future phase)

1. Receive invite link
2. Enter email address as check
3. Log in
4. See class and open it
5. Per module/track dashboard:
   - Introduction notes
   - Video resources
   - Pre-test (must take before starting)
   - Hands-on video upload
   - Case scenario
   - Post-test (same questions as pre-test)
   - Grades (automated)
6. Visibility gated by activity completion

### 4.7 Admin / SME curriculum builder

Dedicated admin pages where subject matter experts can curate universal learning materials and attach them to modules/tracks:
- Manage programs, modules, and tracks
- Attach/detach activities via checkboxes
- Introduction notes
- Video uploads/links
- Pre-test / post-test MCQ builder
- Case scenarios
- Activity templates and rubric checklists (future phase)

### 4.8 Quiz behavior

- Each module/track can have one quiz.
- A quiz can be marked as **pre-test**, **post-test**, or **both**.
- If marked **both**, the mentee takes the same quiz twice: once before the module and once after.
- Correct answers and explanations are shown after submission.
- Both pre-test and post-test scores are recorded.
- An average score is computed.
- Pass mark for competency is 85%.

### 4.9 Roles

Add a new `newbie` role alongside the existing `mentee` role.

| Role | Description |
|------|-------------|
| `super_admin` | All functions |
| County health coordinator | Tied to county |
| Sub-County health coordinator | Tied to subcounty |
| Mentor / Overseer / Supervisor | Tied to mentorships they created and their mentees |
| `mentee` | Health worker enrolled for mentorship; can train newbie if elevated by super admin |
| `newbie` | Complete amateur; eligible to be trained |

---

## 5. Implementation phases

### Phase 1 — Foundation and seed data ✅
- Add `parent_id` to `program_modules` migration.
- Add `newbie` role to `RolePermissionSeeder`.
- Create `EmoncProgramSeeder` to seed:
  - Maternal Health (EmONC) program
  - 13 modules (prefixed `Module X:`)
  - Module 5’s 11 tracks as child `ProgramModule` records (prefixed `Track X:`)
- Update `ProgramModule` model with `parent/children` relationships.
- Update Filament `ProgramModuleResource` to support hierarchy display.

> **Note:** Phase 2 (Activities) was implemented alongside Phase 1.

### Phase 2 — Activities ✅
- Create `activities` table and model (CME, Hands-on Demo, Drill seeded by default).
- Create `program_module_activities` pivot table.
- Add activity attachment UI via checkboxes on the `ProgramModule` edit page.
- Support attaching activities to both modules and tracks.
- Add `ActivityResource` for managing the activity catalog.

### Phase 3 — Content builder ✅
- Create `program_module_contents` table/model.
- Support content types: introduction notes, video URL/upload, case scenario.
- Add content management UI inside `ProgramModuleResource`.
- Add YouTube preview player for video URLs.

### Phase 4 — Quiz system ✅
- Create quiz tables: `program_module_quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `quiz_responses`.
- Build Filament admin UI for MCQ creation.
- Support quiz types: pre-test, post-test, both.
- Mark correct answers and explanations.
- Add quiz preview modal.
- Move quiz resource to its own **Quiz Management** navigation group.
- Backend service to:
  - Submit an attempt
  - Score automatically
  - Show correct answers and results
  - Record pre-test and post-test scores
  - Compute average score

### Phase 5 — Class module expansion ✅
- Update class module creation logic:
  - If selected `ProgramModule` has no children, create one `ClassModule`.
  - If selected `ProgramModule` has children (tracks), create one `ClassModule` per track.
- `ModuleUsageService::getAvailableModules()` now shows only parent modules and hides parents whose tracks are already assigned.
- `ModuleUsageService::assignModulesToClass()` expands parent modules into per-track `ClassModule` records.
- API `POST /api/v1/classes/{class}/modules` updated to use the service and expand tracks; returns an array of created class modules.
- Added `start_date` / `end_date` columns to `program_modules` (curriculum-level) so non-EmONC programs can define module timelines without relying on `class_modules`.
- Removed `start_date` / `end_date` editing from `ClassModule`; these are not used for EmONC.
- On the mentorship creation/edit form, `start_date` / `end_date` are hidden when the selected programme is **Maternal Health (EmONC)**; they remain visible for Infant/Child and Newborn programmes.
- On the class creation/edit form (within a mentorship), `start_date` / `end_date` are also hidden for **Maternal Health (EmONC)** and null-safe when mentorship dates are not set.
- The programme card picker now places the **Maternal Health (EmONC)** card in the 3rd position.
- Class module assignment for **EmONC** uses a custom hierarchical picker that shows modules with their tracks; the parent module checkbox is disabled but ticks when any of its tracks are selected, and only tracks become `ClassModule` records.
- Attached activities, content, and quizzes remain accessible per track through the track's `ProgramModule` relationships.

### Phase 6 — Mentee dashboard implementation
- Extend `/my-class/{class}` to show module/track content.
- Enforce pre-test before module start.
- Show videos, case scenarios, and upload hands-on videos.
- Post-test after module completion.
- Display results and average score.
- Activity-gated visibility.

### Phase 7 — Scoring and certification
- Rubric scoring UI for mentor.
- Two-step certificate approval (mentor → Head DRMH).
- PDF certificate generation.

---

## 6. Open questions / decisions log

| # | Question | Decision |
|---|----------|----------|
| 1 | Program naming | Use **Maternal Health (EmONC)** as the program name. |
| 2 | Track storage | Use self-referential `ProgramModule` with `parent_id` (Option B). |
| 3 | Pre-test / post-test model | Create a new quiz system (`ProgramModuleQuiz`, `QuizQuestion`, `QuizOption`, `QuizAttempt`, `QuizResponse`) — Option A. |
| 4 | Activity attachment | Configurable via checkboxes on `ProgramModule`; attachable to modules and tracks. |
| 5 | Activity unlocking sequence | TBD — backend will support flexible gating; exact sequence decided during mentee dashboard phase. |
| 6 | Hands-on rubric | TBD — configurable checklist per module/track vs generic criteria. |
| 7 | Certificate approval flow | TBD — enforce both Mentor approval and Head DRMH certification, or mentor-only for now. |

---

## 7. Notes for resumption

- Existing Infant Care, Newborn Care, and Child Care programs should remain untouched.
- The new Maternal Health flow will reuse the same tables but add program-specific curriculum content and activity tracking.
- All changes should be implemented via migrations and should be backward-compatible where possible.
- After adding new Filament resources, run `php artisan shield:generate --all` to regenerate permissions.
- The current focus is **backend/admin only**; mentee-facing pages come after the backend is complete.
