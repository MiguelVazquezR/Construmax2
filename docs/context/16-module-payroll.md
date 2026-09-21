# Construmax2 ERP — 16: Payroll & HR Module (Recursos Humanos)

> **Business purpose:** Automate time & attendance and payroll pre-calculation for the company's collaborators: facial biometric kiosk, remote (geolocated) attendance, shifts, automatic lates/overtime, LFT mandatory rest days, incidents, vacations with weekly accrual, real-time pre-payroll, payroll periods with automatic closing, printable payslips and a mirrored expense in *Control de gastos*.
> **Context file covers:** Settings, payroll profiles, authorized devices + kiosk (face recognition), attendance punches, shifts and schedules, holidays, incidents, vacations, payroll periods and adjustments, payslips, the collaborator self-service portal (*Mi asistencia*), the automated artisan commands and the expenses integration.
> **Current scope:** Everything above is implemented. **Not included:** CFDI/SAT stamping, money disbursement, ISR/IMSS calculations, seventh-day/dominical premium, liveness detection (all noted as extension points).

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Settings | `app/Http/Controllers/Payroll/PayrollSettingController.php` + `app/Actions/Payroll/UpdatePayrollSettingsAction.php` | Singleton configuration (period type/anchor, tolerances, overtime multipliers, vacations, facial recognition, retention, remote geolocation) |
| Profiles | `app/Actions/Payroll/SyncPayrollProfileAction.php` + `resources/js/Components/Payroll/PayrollProfileFields.vue` | Payroll profile of a user (employee number auto `EMP-####`, hire date, daily salary/hours, subject flags, remote attendance flag), shared by Users and Technicians forms with permission-aware field filtering |
| Devices | `app/Http/Controllers/Payroll/AttendanceDeviceController.php` + `app/Services/Payroll/AttendanceDeviceService.php` | Authorized kiosk devices: single-issue token (sha256 at rest), last-seen tracking, revocation |
| Kiosk | `app/Http/Controllers/Payroll/KioskController.php` + `resources/js/Pages/Payroll/Kiosk/Index.vue` | Public standalone kiosk: clock, punch type buttons, camera capture and face punch (`face-punch`, 1:N search); the employee number + PIN endpoint (`punch`) remains at API level only |
| Middleware | `app/Http/Middleware/EnsureAttendanceDevice.php` (alias `attendance.device`) | Validates the `X-Attendance-Device` header against active devices |
| Punches | `app/Actions/Payroll/RegisterAttendancePunchAction.php` | Registers a punch (kiosk/remote/manual): subject check, **dismissal check** (no punches after the termination date), duplicate window ±2 min, geolocation requirement for remote, evidence photo to the `capture` media collection |
| Attendance calc | `app/Services/Payroll/AttendanceDayService.php` + `AttendanceDaySummary` (DTO) | Live day summary: worked/paused minutes, late arrival, early leave, overtime, status (`present`/`absent`/`rest_day`/`no_schedule`/`holiday`/`incident`), incident pay fraction |
| Schedules | `app/Services/Payroll/ScheduleResolverService.php` + `ResolvedSchedule` | Resolves the shift of a user for a date (individual assignment > department; rotations advance weekly) |
| Shifts | `app/Http/Controllers/Payroll/ShiftController.php`, `ShiftAssignmentController.php` + `app/Actions/Payroll/AssignUserShiftAction.php` | Shift catalog (fixed, flexible or **por día**: each weekday with its own start/end time and meal minutes; days without a schedule are rest days) and assignments (user or department, optional rotation payload). The individual shift can also be selected from the user/technician forms |
| Holidays | `app/Services/Payroll/HolidayService.php` + `HolidayController` + `SyncHolidayYears` | LFT art. 74 rules (first Monday of Feb, third Monday of Mar/Nov, Dec 1 every 6 years…) + manual days; `payroll:sync-holidays` |
| Incidents | `app/Http/Controllers/Payroll/IncidentController.php` | Absences, medical leaves, paid/unpaid permissions; optional `support` attachment; incident types define default pay behaviour. **No standalone screen**: they are registered, listed and deleted from the payroll period detail (pre-payroll drawer) |
| Vacations | `app/Services/Payroll/VacationService.php`, `RequestVacationAction`, `ReviewVacationRequestAction`, `VacationController` | LFT entitlement table, weekly accrual per season, FIFO consumption, 18-month carryover expiry, working-day validation, approval flow |
| Periods | `app/Services/Payroll/PayrollPeriodService.php` + `PayrollPeriodController` + `resources/js/Pages/Payroll/Periods/Show.vue` + `resources/js/Components/Payroll/EmployeePeriodPanel.vue` | Period creation (first period from settings anchor; semimonthly/weekly/biweekly), KPI stats, live pre-payroll **with the full detail of every collaborator inline** (collapsible panels: days/punches, weekly schedule, incidents, adjustments, receipts), **pre-payroll sheet** (`PrePayroll.vue`, printable), late ignoring, closing, reopening, Excel export. The days endpoint is fetched by each panel the first time it is expanded |
| Calculator | `app/Services/Payroll/PayrollCalculatorService.php` | Pure payroll math per collaborator/period: payable days, overtime 2x/3x split at the weekly threshold, worked-holiday extra, incident fractions, lates (only when `deduct_minutes`), adjustments → snapshot + lines + totals |
| Payslips | `app/Services/Payroll/PayslipService.php` + `PayslipController` + `resources/js/Pages/Payroll/Payslips/Print.vue` | Frozen payslips (snapshot + lines + days) generated at closing; **open periods print the live pre-payroll** instead (marked as preliminary, no folio); printable page of **auto-height** receipts — employee data, concepts with subtotals, attendance summary, highlighted net and signature (browser print → PDF) |
| Closing | `app/Console/Commands/ClosePayrollPeriod.php` (`payroll:close-period`) | Closes the due period: payslips, mirrored expense, notification, opens the next period. Scheduled daily at 01:00 |
| Retention | `app/Console/Commands/PruneAttendanceCaptures.php` (`payroll:prune-captures`) | Deletes attendance captures older than the configured retention window (scheduled Mondays 03:00) |
| Enrollment | `app/Console/Commands/EnrollProfilePhotos.php` (`payroll:enroll-profile-photos`) | Bulk indexes the profile photos of attendance collaborators (users and technicians) as face references; `--user=` limits to one collaborator, `--force` re-indexes |
| Faces | `app/Services/Payroll/FaceRecognition/` (`FaceRecognitionService` contract, `AwsFaceRecognitionService`, `NullFaceRecognitionService`), `EnrollUserFacesAction`, `FaceEnrollmentController` | AWS Rekognition integration (1 collection, index/search/delete), admin enrollment from Users, self-enrollment from *Mi asistencia*, status endpoint |
| Self-service | `app/Http/Controllers/Payroll/MyAttendanceController.php` + `resources/js/Pages/Payroll/MyAttendance/Index.vue` | Remote punch (geolocation + optional face verification), recent history, vacations (balance/seasons/requests), own payslips, face enrollment |
| Adjustment/Audit | `app/Http/Controllers/Payroll/AttendanceLogController.php` | Manual punch corrections with optional `edit_reason` (audited: author + timestamp; a blank reason keeps the previous one) |
| Comments | `app/Http/Controllers/Payroll/PayrollNoteController.php` + `app/Models/PayrollNote.php` (`payroll_notes`) | Free comments about a collaborator inside a period: add, edit and delete them at the bottom of every collaborator panel; they also show in the pre-payroll sheet. Informational only (they do not change the numbers) and require `payroll.periods.manage` |
| Models | `app/Models/`: `PayrollSetting`, `PayrollProfile`, `AttendanceDevice`, `AttendanceLog`, `AttendanceDayOverride`, `Shift`, `ShiftAssignment`, `Holiday`, `Incident`, `VacationRequest`, `PayrollPeriod`, `PayrollAdjustment`, `PayrollNote`, `Payslip`, `PayslipLine`, `PayslipDay`, `FaceEnrollment` | Domain models with scopes (`forUser`, `onDate`, `active`, `overlapping`, `forPeriod`…), label helpers and casts |
| Migrations | `database/migrations/2026_09_19_000001` … `000015` + `2026_09_20_000001` … `000003` | 18 migrations: settings → profiles → devices → logs → overrides → shifts → assignments → holidays → vacations → incidents → periods → adjustments → payslips (3 tables) → `expenses.payroll_period_id` → face enrollments → payroll notes → vacation adjustments → `shifts.day_schedules` |
| Permissions | `database/seeders/PermissionSeeder.php` (category *Nómina*) | `payroll.settings.manage`, `payroll.profiles.manage`, `payroll.remote-attendance.manage`, `payroll.devices.manage`, `payroll.shifts.manage`, `payroll.incidents.manage`, `payroll.vacations.manage`, `payroll.vacations.approve`, `payroll.holidays.manage`, `payroll.periods.index`, `payroll.periods.manage`, `payroll.periods.close`, `payroll.payslips.view`, `payroll.faces.manage` |
| Notifications | `app/Notifications/VacationRequested.php`, `VacationReviewed.php`, `PayrollPeriodClosed.php` | Approval requests (permission-based), review results (direct to requester) and period closures (subscribers of `payroll.period-closed`) |
| Routes | `routes/web/payroll.php` (registered from `routes/web.php`) | Authenticated routes + the public kiosk group |
| Menu | `resources/js/Layouts/AppSidebar.vue` | "Recursos Humanos" submenu (permission-gated) + "Mi asistencia" for collaborators with a payroll profile (`attendance_portal` shared prop) |
| Tests | `tests/Feature/Payroll/` (23 files, 210 tests) | See the Test coverage section below |

---

## Routes

All routes are authenticated (`auth`, `verified`) unless noted. Payroll routes use the `/payroll` prefix and the `payroll.` name prefix.

| Method | URI | Name | Notes |
|--------|-----|------|-------|
| GET/PUT | `/payroll/settings` | `payroll.settings.edit` / `.update` | `payroll.settings.manage` |
| GET/POST | `/payroll/devices` | `payroll.devices.index` / `.store` | List/register the current device (`payroll.devices.manage`); the plain token is returned only once |
| DELETE | `/payroll/devices/{device}` | `payroll.devices.destroy` | Revoke |
| GET/POST/PUT/DELETE | `/payroll/shifts...` | `payroll.shifts.*` | Shift CRUD (`payroll.shifts.manage`) |
| POST/DELETE | `/payroll/shift-assignments...` | `payroll.shift-assignments.*` | User or department assignments |
| GET/POST/DELETE | `/payroll/holidays...` + `POST /holidays/sync` | `payroll.holidays.*` | Holiday catalog and LFT sync |
| POST/DELETE | `/payroll/incidents` + `/payroll/incidents/{incident}` | `payroll.incidents.store` / `.destroy` | `payroll.incidents.manage`; used from the payroll period detail (there is no incidents index route) |
| POST / PUT / DELETE | `/payroll/periods/{period}/notes` + `/payroll/notes/{note}` | `payroll.periods.notes.store` / `payroll.notes.update` / `payroll.notes.destroy` | Comments about a collaborator inside a period (`payroll.periods.manage`) |
| GET | `/payroll/vacations` (+ `balance`) | `payroll.vacations.index` / `.balance` | Manage or approve permission |
| POST/DELETE | `/payroll/vacations/requests...` | `payroll.vacations.requests.store` / `.cancel` | Collaborator requests own days; managers can request for others |
| POST | `/payroll/vacations/{vacationRequest}/approve` / `reject` | `payroll.vacations.approve` / `.reject` | `payroll.vacations.approve` |
| POST/DELETE | `/payroll/users/{user}/vacation-adjustments` + `/payroll/vacation-adjustments/{adjustment}` | `payroll.vacations.adjustments.store` / `.destroy` | Manual movements of the balance: initial balance, granted days and corrections (`payroll.vacations.manage`) |
| GET/POST | `/payroll/periods` | `payroll.periods.index` / `.store` | `payroll.periods.index` to list; creation requires `payroll.periods.close` |
| GET | `/payroll/periods/{period}` (+ `/export`) | `payroll.periods.show` / `.export` | Pre-payroll detail and Excel export |
| GET | `/payroll/periods/{period}/employees/{user}/days` | `payroll.periods.days` | Per-day JSON drawer data (live or frozen); accepts optional `from` / `to` to narrow days and punches (clamped to the period, invalid values fall back to the whole period) |
| PUT | `/payroll/periods/{period}/employees/{user}/override` | `payroll.periods.override` | Ignore late / day notes |
| POST/DELETE | `/payroll/periods/{period}/adjustments` / `/payroll/adjustments/{adjustment}` | `payroll.periods.adjustments.store` / `payroll.adjustments.destroy` | Manual earnings/deductions |
| POST | `/payroll/periods/{period}/close` / `reopen` | `payroll.periods.close` / `.reopen` | `payroll.periods.close`; reopen is blocked if the mirrored expense is paid |
| POST/PUT/DELETE | `/payroll/attendance-logs...` | `payroll.attendance-logs.*` | Manual punch corrections (audited; `edit_reason` optional) |
| POST/DELETE | `/payroll/users/{user}/faces` | `payroll.faces.store` / `.destroy` | Admin face enrollment (`payroll.faces.manage`) |
| POST/DELETE | `/payroll/my-attendance/faces` (+ `GET .../status`) | `payroll.my-attendance.faces.*` | Self-service enrollment |
| GET | `/payroll/my-attendance` | `payroll.my-attendance.index` | Collaborator portal (attendance subjects only) |
| POST | `/payroll/my-attendance/punch` | `payroll.my-attendance.punch` | Remote punch: geolocation + face verification when enabled (throttled 30/min) |
| GET | `/payroll/periods/{period}/pre-payroll` | `payroll.periods.pre-payroll` | Printable pre-nómina **summary** of every collaborator (days to pay, incidents with dates, comments and the head count); live for open periods, frozen for closed ones; always rendered in light mode. Requires `payroll.periods.index` |
| GET | `/payroll/periods/{period}/payslips/print` | `payroll.periods.payslips.print` | Requires `payroll.payslips.view`; collaborators can print **their own** payslip passing `users[]=own_id`. Works for open periods (live pre-payroll) and closed ones (frozen payslips) |
| GET | `/attendance/kiosk` | `attendance.kiosk.show` | **Public** kiosk page |
| POST | `/attendance/kiosk/bootstrap` / `punch` / `face-punch` | `attendance.kiosk.*` | Public but restricted by `attendance.device` middleware + throttle (bootstrap 120/min, punches 30/min) |

---

## Data model (15 migrations)

| Table | Highlights |
|-------|-----------|
| `payroll_settings` | Singleton (row 1). Period type (`weekly`/`biweekly`/`semimonthly`) + anchor date, `late_tolerance_minutes`, `late_discount_mode` (`track_only`/`deduct_minutes`), overtime multipliers (2x/3x) + `overtime_weekly_threshold_hours`, `holiday_worked_extra_multiplier`, `vacation_min_days_to_request`, `vacation_carryover_months` (18), `incapacity_paid` + percentage, `default_daily_hours`, `payroll_expense_category_id`, `face_recognition_enabled` + threshold + collection, `kiosk_pin_fallback_enabled`, `attendance_capture_retention_months`, `remote_geolocation_required`, `updated_by` |
| `payroll_profiles` | One per user: unique `employee_number` (`EMP-####`), `hire_date`, `termination_date` (optional dismissal date), `daily_salary`, `daily_hours`, `is_payroll_subject`, `is_attendance_subject`, `can_remote_attendance`, `kiosk_pin` (hashed cast), notes |
| `attendance_devices` | `token_hash` (sha256, unique), registered by/at, `last_seen_at/ip/user_agent`, `is_active`, revoked by/at |
| `attendance_logs` | `type` (check_in, lunch_start/end, break_start/end, check_out), `punched_at`, `source` (kiosk/remote/manual), `identifier_method` (face/pin/manual), `face_similarity`, lat/lng/accuracy, ip/ua, audit (`edited_by`, `edited_at`, `edit_reason`); evidence in media collection `capture` (single file, optimized) |
| `attendance_day_overrides` | Sparse overrides per user+date: `late_ignored`, notes |
| `shifts` | `type` (fixed/flexible/per_day), `start_time`/`end_time`, `meal_minutes`, `is_meal_paid`, `days` (ISO 1-7 JSON), `day_schedules` (JSON per-weekday schedule: `start_time`, `end_time`, `meal_minutes`), `required_daily_hours` (flexible), shift-level `late_tolerance_minutes`, `is_active` |
| `shift_assignments` | `user_id` **or** `department`, `type`, `shift_id`, `rotation` (weekly cycle of shift ids), `start_date`/`end_date` |
| `holidays` | `date` unique, `name`, `year`, `source` (`lft`/`manual`), `is_mandatory`, `apply_extra_pay` |
| `incidents` | `type` (absence_justified/unjustified, medical_leave, permission_paid/unpaid, vacation, other), date range + `days`, `affects_pay` (nullable = type default), `status`, `vacation_request_id` link; optional `support` attachment |
| `vacation_requests` | Date range, `days`, `status`, requester/reviewer audit and `review_notes` |
| `payroll_periods` | `type`, range, `status` (open/closed), closing audit, totals (gross/deductions/net), `expense_id` |
| `payroll_adjustments` | Period + user, `type` (earning/deduction), `concept`, `amount` |
| `payroll_notes` | Period + user, `body` (free comment), `created_by` author and timestamps — informational, shown at the bottom of every collaborator panel and in the pre-payroll sheet |
| `payslips` (+ `payslip_lines`, `payslip_days`) | Frozen snapshot per user/period (profile data at closing time), totals (days paid/unpaid, lates, overtime, holidays, vacations, incapacity, adjustments), concept lines and day-by-day detail |
| `expenses.payroll_period_id` | Unique FK added to expenses (mirrored period expense); `created_by` became nullable (the closing command runs without an authenticated user) |
| `face_enrollments` | `user_id`, `collection_id`, `face_id`, `external_image_id`, `status` (active/removed/failed), `quality`, enrolled by/at |

---

## Business rules (implemented)

### Attendance & kiosk
- Kiosk page is public; **every** API call requires `X-Attendance-Device` matching an active `attendance_devices` row (token shown once, stored hashed). Unauthorized devices see a registration hint.
- The kiosk page is **face-only**: the collaborator selects the punch type and presses "Registrar con rostro"; the live capture goes to Rekognition (1:N search) and the collaborator is identified by similarity (`identifier_method=face`, the capture is stored as evidence). When the recognition is not enabled/configured the kiosk shows a red notice instead of allowing punches. The employee number + PIN endpoint (`attendance.kiosk.punch`) and the `kiosk_pin_fallback_enabled` setting remain at the API level for other clients/manual use, but the kiosk UI no longer asks for them: the facial error messages never suggest a PIN, the **kiosk PIN input was removed from the profile forms** (and from the user detail view) so nobody can set a new one from the UI — existing stored PINs stay untouched until the endpoint is disabled or the column is cleared.
- Duplicate punches of the same type within ±2 minutes are rejected.
- Remote punches (`/my-attendance`) require `can_remote_attendance`; geolocation is mandatory when `remote_geolocation_required`; when facial recognition is active and configured the photo is required and must match the authenticated user.
- Manual corrections stamp the audit columns; the reason is **optional** (when left blank, the reason stored by a previous correction is preserved).
- The period panel pre-selects the **recommended next punch** of the day (entry → lunch start → lunch end → exit) in the *Agregar registro* dialog and shows a hint with the suggestion; the same recommendation label travels in the day payload (`suggested_next`, computed by `AttendanceLog::nextSuggestedType`).
- Captures older than `attendance_capture_retention_months` are purged weekly by `payroll:prune-captures`.

### Schedules & attendance calculation
- Schedule resolution precedence: individual assignment → department assignment. Rotations advance one shift per completed week from `start_date`. Flexible shifts use `required_daily_hours`. **Per-day shifts** (`type = per_day`) take the start time, end time and meal minutes of the configured weekday (`day_schedules`), so a collaborator can work Monday–Friday 09:00–18:00 and Saturday 09:00–13:00 with its own meal time; days without an entry are rest days and the expected daily minutes are computed per day.
- The shift can be assigned right from the user/technician **forms** (`shift_id` field, gated by `payroll.profiles.manage`): `AssignUserShiftAction` creates the individual assignment starting on the hire date (or updates the current one instead of duplicating it) and the field carries an info icon that opens a summary popover with the worked days and their schedules — no need to open the shifts page.
- The day summary is computed **live** (nothing materialized): worked minutes from the punch sequence, pauses (meal/break) subtracted unless `is_meal_paid`, late minutes beyond the shift tolerance (or global setting), early leave, overtime beyond the expected day.
- Day status: `present`, `absent` (workday without punches), `rest_day`, `no_schedule`, `holiday` (worked holiday still counts for the extra pay), `incident` (fractional pay per incident type, e.g. incapacity 60%).
- Overrides (`late_ignored`) apply on top of the computed values.

### Holidays
- LFT art. 74 rules are generated by `payroll:sync-holidays` (idempotent; run yearly on Dec 1 and seeded for current+next year): Jan 1, first Monday of February, third Monday of March, May 1, Sep 16, third Monday of November, Dec 25 and Dec 1 every 6 years (from 2024). Manual days can be added/removed.

### Vacations
- LFT entitlement by year of service (12, 14, 16, 18, 20… +2 every 5 years). Seasons run from the hire anniversary; accrual is weekly (entitled ÷ 52 × completed weeks).
- Approved requests consume days **FIFO** from the oldest season; leftovers expire `vacation_carryover_months` after the season end (`addMonthsNoOverflow` semantics).
- Requests validate: minimum days (`vacation_min_days_to_request`), available balance, working days (rest days/holidays excluded) and overlap with blocking requests. Approvals create a linked `vacation` incident so payroll knows the days are paid.
- **Manual movements of the balance** (`vacation_adjustments`): the payroll team can register the *saldo inicial* of a collaborator, extra granted days and positive/negative corrections (one row per movement with days, reason, author and timestamp; type `initial` / `grant` / `adjustment`, only the adjustment accepts negative days). They **never expire**, they are consumed **after every season accrual** (LFT days are used first) and they also work when the profile has no hire date. They are registered from the user profile (*Vacaciones* card in *Información general*) and from the *Saldos por temporada* tab of the vacations screen; both screens list the movements with their author and allow deleting them (the balance is always calculated live, so deleting the row recalculates it). `VacationService::balanceFor()` exposes `adjustment_days` (net) and `adjustment_available_days` (remaining pool after consumption).

### Payroll periods & payslips
- Period types: weekly, biweekly (15-day), semimonthly (1-15 / 16-end of month); the first period is created from the settings anchor.
- `payroll:close-period` runs daily at 01:00: closes the period that ended, freezes one payslip per payroll subject (snapshot + lines + days), creates the **mirrored expense** in *Control de gastos* (total net, configurable category), notifies `payroll.period-closed` subscribers and opens the next period. `--period=` forces a manual close.
- Reopening deletes the frozen payslips and the linked expense — blocked when the expense was paid; the expense cannot be deleted or marked paid from the expenses module while linked.
- **Receipts no longer require closing the period**: `PayslipService::printPayloads()` returns the frozen payslips for a closed period and the live pre-payroll (same numbers as the on-screen rows) for an open one. The print page marks those slips as *Pre-nómina* (no folio + a preliminary note). In *Periodos nómina* the `Recibos` button prints every collaborator and each row has a `Recibo` button that prints a single one (`users[]`).
- **Pre-nómina sheet** (`Pre-nómina` button in the period header): `PayslipService::prePayrollPayloads()` (same builder as the print payloads) plus the period comments and incidents feed `Payroll/Periods/PrePayroll.vue`, a printable A4 landscape **summary** — one row per collaborator with days to pay (and unpaid days), the incidents of the period (type, date range, days with/without pay and notes) **together with the holiday days paid by law** (listed as `Día festivo` with the holiday name and a `trabajado (pago extra)` hint when the day was worked; they count in the incidents counters) and the comments (author + timestamp), a totals row with the head count, and KPI cards (collaborators, days to pay, incidents, comments). It has **no expandable detail** (the full detail lives in the period screen) and it is **light-only**: the page removes the dark theme while it is open and restores the user preference when leaving. The amounts (percepciones, deducciones, neto) are intentionally not listed; a footnote points to the period detail.
- **Manage every collaborator from the period screen**: the period page keeps the KPI cards and shows one collapsible panel per collaborator (`Components/Payroll/EmployeePeriodPanel.vue`) with their own tabs. The collapse header carries an initials avatar, the name/employee data and the summary (days paid/unpaid, lates, overtime, vacations, incapacity, adjustments, incident/comment counters and the net amount in a highlighted pill); `Expandir todos` / `Contraer todos` open or close them in bulk, and the detail is fetched per panel on first expansion (`payroll.periods.days`). The **Días y registros** tab is a full-width table — day (weekday, date, shift), status tag with the day notes, workday window with meal break (`entrada → salida`), a **Registros** column with the record count that opens a per-day dialog (every record with its time, type — entrada/salida/comida/permiso —, source, device, face/PIN and edited flag with its audit reason, plus the evidence photo, Google Maps link and edit/delete actions, and an `Agregar registro` shortcut that pre-fills the selected day — the add/edit dialogs use **separate date and time inputs**), worked vs expected time, lateness with the inline `Ignorar` checkbox (strikethrough + `Ignorado` tag when ignored) and overtime — preceded by four range cards (days shown, time worked, lates, overtime). Evidence (photo/map) is only available inside the per-day records dialog. Status tags are **color-coded by type** (the live day payload exposes `incident_type_key`): green for present days and holidays, amber for vacations and unpaid permissions, blue for justified absences and paid permissions, red for unjustified absences (and missing workdays) and neutral for rest days, days without schedule and medical leaves; the incidents tab and the pre-payroll sheet reuse the same palette. **Horario semanal** shows the current week (shift, schedule, expected hours, workday tag); **Incidencias** lists the period incidents with type tag, dates, pay behaviour, notes and receipt/delete. The same panel keeps the audited per-punch actions, incidents register/delete, manual adjustments, the comments thread (author avatars, inline edit/delete) and that collaborator's receipt.
- **Date filter**: a `Filtrar por fecha` range picker (limited to the period dates) narrows the days and punches of **every** collaborator — the panels re-fetch with `from` / `to` and `Ver todo el periodo` restores the full range. KPI cards and the panel header summaries keep the period totals. Beside it, a **collaborator filter** (searchable multi-select with tags, `collapse-tags-tooltip`) shows only the selected collaborators' panels and opens them automatically, while the totals keep the whole period. The selection also travels to the `Recibos` and `Pre-nómina` buttons as a `users[]` query parameter (`PayrollPeriodController@prePayroll` and `PayslipController@print` accept an array or a comma-separated list), so both pages print **only the selected collaborators**; with no selection they include everyone. The header carries **previous/next period** buttons (the `previousPeriod` / `nextPeriod` props) so periods can be browsed without going back to the index.
- **Incidents stay editable on closed periods**: the *Incidencias* tab of every collaborator panel accepts adding and deleting incidents even when the period is closed (the frozen payslips keep their figures until a *Reabrir periodo*; punch corrections and adjustments remain limited to open periods).
- **Alphabetical order**: collaborators are always listed by name in every payroll screen — period rows/panels, pre-payroll sheet and receipts — including the frozen payslips of closed periods (`payrollSubjects()` already ordered by name; the closed-period rows and payloads are sorted after fetching).
- **Terminology**: attendance punches are labeled *registro(s)* everywhere in the UI and in the flash/validation messages (buttons, columns, dialogs, kiosk copy); the old *marcaje* wording was fully replaced.
- **Comments per collaborator** (`payroll_notes`): added at the bottom of each collaborator panel, with author and timestamp, editable and removable in place by anyone with `payroll.periods.manage`. They are informational (they never change the pre-payroll numbers) and they also travel to the pre-payroll sheet (dedicated *Comentarios* column with the full text).
- Calculation notes: daily salary basis; overtime split 2x until the weekly threshold (default 9 h) then 3x; worked holidays pay an extra × multiplier; incidents (vacations and paid permissions pay full days; medical leaves their configured percentage; justified absences, unpaid permissions and unjustified absences are not paid) pay their fraction on scheduled workdays and **also when the collaborator has no schedule assigned**, whose registered incidents are the only source of truth for those days — so vacations count as paid days for collaborators without an assigned shift (they add to days paid and, when the type is `vacation`, to the vacation days); lates only discount money in `deduct_minutes` mode; no ISR/IMSS.
- Print view uses browser print (`@media print`, `@page A4` margins) → "Save as PDF". Receipts (`Payslips/Print.vue`) are modern **auto-height** cards: header with folio and period, the employee fields (name, number, position, department, daily salary), percepciones/deducciones columns with subtotals, the attendance summary (days paid/unpaid, lates, overtime, vacations, incapacity), a totals box with the net highlighted in the brand colour and a signature line (`Recibí de conformidad`, plus the preliminary note for open periods). The height adapts to the content (text never gets clipped) and `break-inside: avoid` keeps every receipt whole across pages. The receipts page is also **light-only** (same dark-theme removal as the pre-payroll sheet); the person profile photo uploader (`Components/Forms/PhotoUploader.vue`) is shared by the user and technician forms, and the payroll/attendance fields (`Components/Payroll/PayrollProfileFields.vue`) are grouped in **Colaborador / Nómina / Asistencia** cards with switch tiles and helper texts.
- Payroll subjects: `PayrollCalculatorService::payrollSubjects($onDate)` returns the collaborators whose profile is still valid on that date (`PayrollProfile::scopePayrollSubjectOn`): from `hire_date` up to and including `termination_date`. The period screens pass the period start date, so a collaborator dismissed before the period disappears from the rows, the pre-payroll and the payslips, while the historical periods keep their data.
- Employment window inside a period: `calculateFor()` skips the days before `hire_date` and stops at `termination_date`, so the days after the dismissal are never counted as absences or paid days. The period rows and the pre-payroll show a `Baja dd/mm` badge when the collaborator was dismissed inside the period.
- Dismissed collaborators cannot register attendance either: `RegisterAttendancePunchAction` rejects kiosk (PIN and face) and remote punches dated after the termination date, `StoreAttendanceLogRequest` rejects manual punches after it (corrections on or before that date still work) and the *Mi asistencia* portal shows a notice with the punch form hidden (`profile.can_punch`). Their history, vacations and receipts remain available.

### Faces (AWS Rekognition)
- `FaceRecognitionService` contract with an AWS implementation (CreateCollection/IndexFaces/SearchFacesByImage/DeleteFaces) and a null implementation; bound in `AppServiceProvider` depending on `services.aws` credentials (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, collection name via `PAYROLL_REKOGNITION_COLLECTION` or `AWS_REKOGNITION_COLLECTION_ID`).
- Enrollment replaces previous active references (up to 3 photos, quality stored). Admin flow from Users/Show; self-service from *Mi asistencia*. Kiosk and remote punches use the search result.
- The **profile photo** uploaded in the Users **and Technicians** forms acts as the face reference when the module is active (`EnrollProfilePhotoAction`, invoked from `UserController` and `TechnicianController`): the portrait is indexed with `external_image_id = user id` and replaces the previous references only after a successful detection — a photo without a detectable face leaves the old references untouched and the flash message reports the outcome. Collaborators that already have photos can be indexed in bulk with `php artisan payroll:enroll-profile-photos --force` (requires `face_recognition_enabled` + credentials and the collaborator's "Registra asistencia" flag). The live-capture dialog (up to 3 photos) remains available for corrections.

---

## Commands & schedule

| Command | Schedule | Purpose |
|---------|----------|---------|
| `payroll:sync-holidays {year?}` | Yearly on Dec 1 at 02:00 | Generates LFT mandatory rest days |
| `payroll:close-period {--period=}` | Daily at 01:00 | Closes the due period, generates payslips + expense, opens the next |
| `payroll:prune-captures` | Weekly on Monday at 03:00 | Purges attendance captures past the retention window |
| `payroll:enroll-profile-photos {--user=} {--force}` | Manual | Indexes the profile photos of attendance collaborators (users and technicians) as face references in Rekognition; `--force` re-indexes collaborators that already have an active face |

---

## Test coverage (`tests/Feature/Payroll/`, 24 files, 224 tests)

- Settings & profiles: `PayrollSettingsControllerTest` (6), `PayrollProfileTest` (10)
- Attendance: `AttendanceDeviceControllerTest` (5), `KioskAttendanceTest` (11), `AttendanceDayServiceTest` (12), `ScheduleResolverServiceTest` (7)
- Shifts: `ShiftControllerTest` (12), `AssignUserShiftActionTest` (4)
- Holidays: `HolidayServiceTest` (5), `HolidayControllerTest` (7)
- Vacations: `VacationServiceTest` (13), `VacationControllerTest` (10), `VacationAdjustmentControllerTest` (8)
- Incidents: `IncidentControllerTest` (5), `PayrollNoteControllerTest` (7), `PayrollPeriodControllerTest` also asserts the incidents and the comments exposed by the period detail
- Payroll: `PayrollCalculatorServiceTest` (12), `PayrollPeriodServiceTest` (11), `PayrollPeriodControllerTest` (26), `PayslipControllerTest` (8)
- Faces & portal: `FaceRecognitionTest` (13), `MyAttendanceTest` (12)
- Retention: `PruneAttendanceCapturesTest` (2)

---

## Notes / gotchas

- HTTPS is required in production for camera and geolocation (localhost exempt). The kiosk is face-only, so the kiosk device needs a working camera (the PIN endpoints remain API-level only).
- The AWS binding falls back to the null provider when credentials are missing; the UI shows warnings and enrollment/punches return validation errors in that state.
- SQLite (test env) stores date casts as strings: always use `whereDate` in scopes and validation closures for uniqueness.
- Carbon 3 returns signed diffs (`abs()` needed) and `addMonths` overflows — use `addMonthsNoOverflow` for vacation expiry.
- `expenses.created_by` is nullable; the expenses UI renders "Sistema" for command-generated expenses.
- Out of scope (quote): CFDI/SAT, money disbursement, ISR/IMSS, seventh-day premium, liveness detection.
