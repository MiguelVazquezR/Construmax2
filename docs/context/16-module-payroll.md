# Construmax2 ERP — 16: Payroll & HR Module (Recursos Humanos)

> **Business purpose:** Automate time & attendance and payroll pre-calculation for the company's collaborators: facial biometric kiosk, remote (geolocated) attendance, shifts, automatic lates/overtime, LFT mandatory rest days, incidents, vacations with weekly accrual, real-time pre-payroll, payroll periods with automatic closing, printable payslips and a mirrored expense in *Control de gastos*.
> **Context file covers:** Settings, payroll profiles, authorized devices + kiosk (PIN/face), attendance punches, shifts and schedules, holidays, incidents, vacations, payroll periods and adjustments, payslips, the collaborator self-service portal (*Mi asistencia*), the automated artisan commands and the expenses integration.
> **Current scope:** Everything above is implemented. **Not included:** CFDI/SAT stamping, money disbursement, ISR/IMSS calculations, seventh-day/dominical premium, liveness detection (all noted as extension points).

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Settings | `app/Http/Controllers/Payroll/PayrollSettingController.php` + `app/Actions/Payroll/UpdatePayrollSettingsAction.php` | Singleton configuration (period type/anchor, tolerances, overtime multipliers, vacations, facial recognition, retention, remote geolocation) |
| Profiles | `app/Actions/Payroll/SyncPayrollProfileAction.php` + `resources/js/Components/Payroll/PayrollProfileFields.vue` | Payroll profile of a user (employee number auto `EMP-####`, hire date, daily salary/hours, subject flags, remote attendance flag, kiosk PIN), shared by Users and Technicians forms with permission-aware field filtering |
| Devices | `app/Http/Controllers/Payroll/AttendanceDeviceController.php` + `app/Services/Payroll/AttendanceDeviceService.php` | Authorized kiosk devices: single-issue token (sha256 at rest), last-seen tracking, revocation |
| Kiosk | `app/Http/Controllers/Payroll/KioskController.php` + `resources/js/Pages/Payroll/Kiosk/Index.vue` | Public standalone kiosk: clock, punch type buttons, camera capture, face punch (`face-punch`, 1:N search) and PIN fallback (`punch`) |
| Middleware | `app/Http/Middleware/EnsureAttendanceDevice.php` (alias `attendance.device`) | Validates the `X-Attendance-Device` header against active devices |
| Punches | `app/Actions/Payroll/RegisterAttendancePunchAction.php` | Registers a punch (kiosk/remote/manual): subject check, duplicate window ±2 min, geolocation requirement for remote, evidence photo to the `capture` media collection |
| Attendance calc | `app/Services/Payroll/AttendanceDayService.php` + `AttendanceDaySummary` (DTO) | Live day summary: worked/paused minutes, late arrival, early leave, overtime, status (`present`/`absent`/`rest_day`/`no_schedule`/`holiday`/`incident`), incident pay fraction |
| Schedules | `app/Services/Payroll/ScheduleResolverService.php` + `ResolvedSchedule` | Resolves the shift of a user for a date (individual assignment > department; rotations advance weekly) |
| Shifts | `app/Http/Controllers/Payroll/ShiftController.php`, `ShiftAssignmentController.php` | Shift catalog (fixed/rotating/flexible with per-weekday times) and assignments (user or department, optional rotation payload) |
| Holidays | `app/Services/Payroll/HolidayService.php` + `HolidayController` + `SyncHolidayYears` | LFT art. 74 rules (first Monday of Feb, third Monday of Mar/Nov, Dec 1 every 6 years…) + manual days; `payroll:sync-holidays` |
| Incidents | `app/Http/Controllers/Payroll/IncidentController.php` | Absences, medical leaves, paid/unpaid permissions; optional `support` attachment; incident types define default pay behaviour |
| Vacations | `app/Services/Payroll/VacationService.php`, `RequestVacationAction`, `ReviewVacationRequestAction`, `VacationController` | LFT entitlement table, weekly accrual per season, FIFO consumption, 18-month carryover expiry, working-day validation, approval flow |
| Periods | `app/Services/Payroll/PayrollPeriodService.php` + `PayrollPeriodController` | Period creation (first period from settings anchor; semimonthly/weekly/biweekly), live pre-payroll rows, per-day drawer, late ignoring, adjustments, closing, reopening, Excel export |
| Calculator | `app/Services/Payroll/PayrollCalculatorService.php` | Pure payroll math per collaborator/period: payable days, overtime 2x/3x split at the weekly threshold, worked-holiday extra, incident fractions, lates (only when `deduct_minutes`), adjustments → snapshot + lines + totals |
| Payslips | `app/Services/Payroll/PayslipService.php` + `PayslipController` + `resources/js/Pages/Payroll/Payslips/Print.vue` | Frozen payslips (snapshot + lines + days) generated at closing; compact 4-5-per-sheet printable page (browser print → PDF) |
| Closing | `app/Console/Commands/ClosePayrollPeriod.php` (`payroll:close-period`) | Closes the due period: payslips, mirrored expense, notification, opens the next period. Scheduled daily at 01:00 |
| Retention | `app/Console/Commands/PruneAttendanceCaptures.php` (`payroll:prune-captures`) | Deletes attendance captures older than the configured retention window (scheduled Mondays 03:00) |
| Faces | `app/Services/Payroll/FaceRecognition/` (`FaceRecognitionService` contract, `AwsFaceRecognitionService`, `NullFaceRecognitionService`), `EnrollUserFacesAction`, `FaceEnrollmentController` | AWS Rekognition integration (1 collection, index/search/delete), admin enrollment from Users, self-enrollment from *Mi asistencia*, status endpoint |
| Self-service | `app/Http/Controllers/Payroll/MyAttendanceController.php` + `resources/js/Pages/Payroll/MyAttendance/Index.vue` | Remote punch (geolocation + optional face verification), recent history, vacations (balance/seasons/requests), own payslips, face enrollment |
| Adjustment/Audit | `app/Http/Controllers/Payroll/AttendanceLogController.php` | Manual punch corrections with mandatory `edit_reason` (audited) |
| Models | `app/Models/`: `PayrollSetting`, `PayrollProfile`, `AttendanceDevice`, `AttendanceLog`, `AttendanceDayOverride`, `Shift`, `ShiftAssignment`, `Holiday`, `Incident`, `VacationRequest`, `PayrollPeriod`, `PayrollAdjustment`, `Payslip`, `PayslipLine`, `PayslipDay`, `FaceEnrollment` | Domain models with scopes (`forUser`, `onDate`, `active`, `overlapping`…), label helpers and casts |
| Migrations | `database/migrations/2026_09_19_000001` … `000015` | 15 migrations: settings → profiles → devices → logs → overrides → shifts → assignments → holidays → vacations → incidents → periods → adjustments → payslips (3 tables) → `expenses.payroll_period_id` → face enrollments |
| Permissions | `database/seeders/PermissionSeeder.php` (category *Nómina*) | `payroll.settings.manage`, `payroll.profiles.manage`, `payroll.remote-attendance.manage`, `payroll.devices.manage`, `payroll.shifts.manage`, `payroll.incidents.manage`, `payroll.vacations.manage`, `payroll.vacations.approve`, `payroll.holidays.manage`, `payroll.periods.index`, `payroll.periods.manage`, `payroll.periods.close`, `payroll.payslips.view`, `payroll.faces.manage` |
| Notifications | `app/Notifications/VacationRequested.php`, `VacationReviewed.php`, `PayrollPeriodClosed.php` | Approval requests (permission-based), review results (direct to requester) and period closures (subscribers of `payroll.period-closed`) |
| Routes | `routes/web/payroll.php` (registered from `routes/web.php`) | Authenticated routes + the public kiosk group |
| Menu | `resources/js/Layouts/AppSidebar.vue` | "Recursos Humanos" submenu (permission-gated) + "Mi asistencia" for collaborators with a payroll profile (`attendance_portal` shared prop) |
| Tests | `tests/Feature/Payroll/` (18 files, 156 tests) | See the Test coverage section below |

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
| GET/POST/DELETE | `/payroll/incidents...` | `payroll.incidents.*` | `payroll.incidents.manage` |
| GET | `/payroll/vacations` (+ `balance`) | `payroll.vacations.index` / `.balance` | Manage or approve permission |
| POST/DELETE | `/payroll/vacations/requests...` | `payroll.vacations.requests.store` / `.cancel` | Collaborator requests own days; managers can request for others |
| POST | `/payroll/vacations/{vacationRequest}/approve` / `reject` | `payroll.vacations.approve` / `.reject` | `payroll.vacations.approve` |
| GET/POST | `/payroll/periods` | `payroll.periods.index` / `.store` | `payroll.periods.index` to list; creation requires `payroll.periods.close` |
| GET | `/payroll/periods/{period}` (+ `/export`) | `payroll.periods.show` / `.export` | Pre-payroll detail and Excel export |
| GET | `/payroll/periods/{period}/employees/{user}/days` | `payroll.periods.days` | Per-day JSON drawer data (live or frozen) |
| PUT | `/payroll/periods/{period}/employees/{user}/override` | `payroll.periods.override` | Ignore late / day notes |
| POST/DELETE | `/payroll/periods/{period}/adjustments` / `/payroll/adjustments/{adjustment}` | `payroll.periods.adjustments.store` / `payroll.adjustments.destroy` | Manual earnings/deductions |
| POST | `/payroll/periods/{period}/close` / `reopen` | `payroll.periods.close` / `.reopen` | `payroll.periods.close`; reopen is blocked if the mirrored expense is paid |
| POST/PUT/DELETE | `/payroll/attendance-logs...` | `payroll.attendance-logs.*` | Manual punch corrections (audited, `edit_reason` required) |
| POST/DELETE | `/payroll/users/{user}/faces` | `payroll.faces.store` / `.destroy` | Admin face enrollment (`payroll.faces.manage`) |
| POST/DELETE | `/payroll/my-attendance/faces` (+ `GET .../status`) | `payroll.my-attendance.faces.*` | Self-service enrollment |
| GET | `/payroll/my-attendance` | `payroll.my-attendance.index` | Collaborator portal (attendance subjects only) |
| POST | `/payroll/my-attendance/punch` | `payroll.my-attendance.punch` | Remote punch: geolocation + face verification when enabled (throttled 30/min) |
| GET | `/payroll/periods/{period}/payslips/print` | `payroll.periods.payslips.print` | Requires `payroll.payslips.view`; collaborators can print **their own** payslip passing `users[]=own_id` |
| GET | `/attendance/kiosk` | `attendance.kiosk.show` | **Public** kiosk page |
| POST | `/attendance/kiosk/bootstrap` / `punch` / `face-punch` | `attendance.kiosk.*` | Public but restricted by `attendance.device` middleware + throttle (bootstrap 120/min, punches 30/min) |

---

## Data model (15 migrations)

| Table | Highlights |
|-------|-----------|
| `payroll_settings` | Singleton (row 1). Period type (`weekly`/`biweekly`/`semimonthly`) + anchor date, `late_tolerance_minutes`, `late_discount_mode` (`track_only`/`deduct_minutes`), overtime multipliers (2x/3x) + `overtime_weekly_threshold_hours`, `holiday_worked_extra_multiplier`, `vacation_min_days_to_request`, `vacation_carryover_months` (18), `incapacity_paid` + percentage, `default_daily_hours`, `payroll_expense_category_id`, `face_recognition_enabled` + threshold + collection, `kiosk_pin_fallback_enabled`, `attendance_capture_retention_months`, `remote_geolocation_required`, `updated_by` |
| `payroll_profiles` | One per user: unique `employee_number` (`EMP-####`), `hire_date`, `daily_salary`, `daily_hours`, `is_payroll_subject`, `is_attendance_subject`, `can_remote_attendance`, `kiosk_pin` (hashed cast), notes |
| `attendance_devices` | `token_hash` (sha256, unique), registered by/at, `last_seen_at/ip/user_agent`, `is_active`, revoked by/at |
| `attendance_logs` | `type` (check_in, lunch_start/end, break_start/end, check_out), `punched_at`, `source` (kiosk/remote/manual), `identifier_method` (face/pin/manual), `face_similarity`, lat/lng/accuracy, ip/ua, audit (`edited_by`, `edited_at`, `edit_reason`); evidence in media collection `capture` (single file, optimized) |
| `attendance_day_overrides` | Sparse overrides per user+date: `late_ignored`, notes |
| `shifts` | `type` (fixed/rotating/flexible), `start_time`/`end_time`, `meal_minutes`, `is_meal_paid`, `days` (ISO 1-7 JSON), `required_daily_hours` (flexible), shift-level `late_tolerance_minutes`, `is_active` |
| `shift_assignments` | `user_id` **or** `department`, `type`, `shift_id`, `rotation` (weekly cycle of shift ids), `start_date`/`end_date` |
| `holidays` | `date` unique, `name`, `year`, `source` (`lft`/`manual`), `is_mandatory`, `apply_extra_pay` |
| `incidents` | `type` (absence_justified/unjustified, medical_leave, permission_paid/unpaid, vacation, other), date range + `days`, `affects_pay` (nullable = type default), `status`, `vacation_request_id` link; optional `support` attachment |
| `vacation_requests` | Date range, `days`, `status`, requester/reviewer audit and `review_notes` |
| `payroll_periods` | `type`, range, `status` (open/closed), closing audit, totals (gross/deductions/net), `expense_id` |
| `payroll_adjustments` | Period + user, `type` (earning/deduction), `concept`, `amount` |
| `payslips` (+ `payslip_lines`, `payslip_days`) | Frozen snapshot per user/period (profile data at closing time), totals (days paid/unpaid, lates, overtime, holidays, vacations, incapacity, adjustments), concept lines and day-by-day detail |
| `expenses.payroll_period_id` | Unique FK added to expenses (mirrored period expense); `created_by` became nullable (the closing command runs without an authenticated user) |
| `face_enrollments` | `user_id`, `collection_id`, `face_id`, `external_image_id`, `status` (active/removed/failed), `quality`, enrolled by/at |

---

## Business rules (implemented)

### Attendance & kiosk
- Kiosk page is public; **every** API call requires `X-Attendance-Device` matching an active `attendance_devices` row (token shown once, stored hashed). Unauthorized devices see a registration hint.
- Verified punches: face (1:N search against the Rekognition collection; `identifier_method=face` + similarity) or employee number + PIN fallback (when `kiosk_pin_fallback_enabled`). Both store the captured frame as evidence. When the fallback is disabled the kiosk hides the employee number + PIN form and works face-only (a red notice appears if no method is enabled at all).
- Duplicate punches of the same type within ±2 minutes are rejected.
- Remote punches (`/my-attendance`) require `can_remote_attendance`; geolocation is mandatory when `remote_geolocation_required`; when facial recognition is active and configured the photo is required and must match the authenticated user.
- Manual corrections require a reason and stamp the audit columns.
- Captures older than `attendance_capture_retention_months` are purged weekly by `payroll:prune-captures`.

### Schedules & attendance calculation
- Schedule resolution precedence: individual assignment → department assignment. Rotations advance one shift per completed week from `start_date`. Flexible shifts use `required_daily_hours`.
- The day summary is computed **live** (nothing materialized): worked minutes from the punch sequence, pauses (meal/break) subtracted unless `is_meal_paid`, late minutes beyond the shift tolerance (or global setting), early leave, overtime beyond the expected day.
- Day status: `present`, `absent` (workday without punches), `rest_day`, `no_schedule`, `holiday` (worked holiday still counts for the extra pay), `incident` (fractional pay per incident type, e.g. incapacity 60%).
- Overrides (`late_ignored`) apply on top of the computed values.

### Holidays
- LFT art. 74 rules are generated by `payroll:sync-holidays` (idempotent; run yearly on Dec 1 and seeded for current+next year): Jan 1, first Monday of February, third Monday of March, May 1, Sep 16, third Monday of November, Dec 25 and Dec 1 every 6 years (from 2024). Manual days can be added/removed.

### Vacations
- LFT entitlement by year of service (12, 14, 16, 18, 20… +2 every 5 years). Seasons run from the hire anniversary; accrual is weekly (entitled ÷ 52 × completed weeks).
- Approved requests consume days **FIFO** from the oldest season; leftovers expire `vacation_carryover_months` after the season end (`addMonthsNoOverflow` semantics).
- Requests validate: minimum days (`vacation_min_days_to_request`), available balance, working days (rest days/holidays excluded) and overlap with blocking requests. Approvals create a linked `vacation` incident so payroll knows the days are paid.

### Payroll periods & payslips
- Period types: weekly, biweekly (15-day), semimonthly (1-15 / 16-end of month); the first period is created from the settings anchor.
- `payroll:close-period` runs daily at 01:00: closes the period that ended, freezes one payslip per payroll subject (snapshot + lines + days), creates the **mirrored expense** in *Control de gastos* (total net, configurable category), notifies `payroll.period-closed` subscribers and opens the next period. `--period=` forces a manual close.
- Reopening deletes the frozen payslips and the linked expense — blocked when the expense was paid; the expense cannot be deleted or marked paid from the expenses module while linked.
- Print view uses browser print (`@media print`, `@page A4` margins), compact 52 mm slips, 4-5 per sheet → "Save as PDF".
- Calculation notes: daily salary basis; overtime split 2x until the weekly threshold (default 9 h) then 3x; worked holidays pay an extra × multiplier; lates only discount money in `deduct_minutes` mode; no ISR/IMSS.

### Faces (AWS Rekognition)
- `FaceRecognitionService` contract with an AWS implementation (CreateCollection/IndexFaces/SearchFacesByImage/DeleteFaces) and a null implementation; bound in `AppServiceProvider` depending on `services.aws` credentials (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `PAYROLL_REKOGNITION_COLLECTION`).
- Enrollment replaces previous active references (up to 3 photos, quality stored). Admin flow from Users/Show; self-service from *Mi asistencia*. Kiosk and remote punches use the search result.
- The **profile photo** uploaded in the Users form acts as the face reference when the module is active (`EnrollProfilePhotoAction`, invoked from `UserController`): the portrait is indexed with `external_image_id = user id` and replaces the previous references only after a successful detection — a photo without a detectable face leaves the old references untouched and the flash message reports the outcome. The live-capture dialog (up to 3 photos) remains available for corrections.

---

## Commands & schedule

| Command | Schedule | Purpose |
|---------|----------|---------|
| `payroll:sync-holidays {year?}` | Yearly on Dec 1 at 02:00 | Generates LFT mandatory rest days |
| `payroll:close-period {--period=}` | Daily at 01:00 | Closes the due period, generates payslips + expense, opens the next |
| `payroll:prune-captures` | Weekly on Monday at 03:00 | Purges attendance captures past the retention window |

---

## Test coverage (`tests/Feature/Payroll/`, 18 files, 156 tests)

- Settings & profiles: `PayrollSettingsControllerTest` (6), `PayrollProfileTest` (8)
- Attendance: `AttendanceDeviceControllerTest` (5), `KioskAttendanceTest` (9), `AttendanceDayServiceTest` (12), `ScheduleResolverServiceTest` (7)
- Shifts: `ShiftControllerTest` (10)
- Holidays: `HolidayServiceTest` (5), `HolidayControllerTest` (7)
- Vacations: `VacationServiceTest` (8), `VacationControllerTest` (10)
- Incidents: `IncidentControllerTest` (7)
- Payroll: `PayrollCalculatorServiceTest` (9), `PayrollPeriodServiceTest` (11), `PayrollPeriodControllerTest` (15), `PayslipControllerTest` (4)
- Faces & portal: `FaceRecognitionTest` (13), `MyAttendanceTest` (10)
- Retention: `PruneAttendanceCapturesTest` (2)

---

## Notes / gotchas

- HTTPS is required in production for camera and geolocation (localhost exempt). The kiosk also works with PIN only when no camera is available.
- The AWS binding falls back to the null provider when credentials are missing; the UI shows warnings and enrollment/punches return validation errors in that state.
- SQLite (test env) stores date casts as strings: always use `whereDate` in scopes and validation closures for uniqueness.
- Carbon 3 returns signed diffs (`abs()` needed) and `addMonths` overflows — use `addMonthsNoOverflow` for vacation expiry.
- `expenses.created_by` is nullable; the expenses UI renders "Sistema" for command-generated expenses.
- Out of scope (quote): CFDI/SAT, money disbursement, ISR/IMSS, seventh-day premium, liveness detection.
