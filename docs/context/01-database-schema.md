# Construmax2 ERP — 01: Database Schema

> Summarizes all ~47 tables grouped by domain. Focuses on relationships and business-meaningful columns.

---

## Domain: Auth & Sessions

### `users`
Core authentication table extended by Jetstream/Sanctum.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string | unique, nullable |
| `email_verified_at` | timestamp | nullable |
| `password` | string | hashed |
| `is_active` | boolean | default `true` |
| `two_factor_secret` | text | nullable (2FA) |
| `two_factor_recovery_codes` | text | nullable |
| `two_factor_confirmed_at` | timestamp | nullable |
| `profile_photo_path` | string(2048) | nullable |

**Relationships:** `hasOne(Employee)`, `hasOne(Technician)`, `hasMany(Ticket, seller_id)`, `belongsToMany(Calendar, calendar_participants)`

### `personal_access_tokens`
Sanctum API tokens (standard schema).

### `sessions`
Laravel session storage (database driver).

### `password_reset_tokens`
Standard Laravel password reset tokens.

### `cache` & `cache_locks`
Laravel cache tables (database driver).

### `jobs` & `job_batches` & `failed_jobs`
Laravel queue tables.

---

## Domain: Employees

### `employees`
| Column | Type | Key |
|--------|------|-----|
| `id` | bigint PK | |
| `user_id` | FK → users.id ON DELETE CASCADE | One employee per user |
| `department` | string | |
| `position` | string | |
| `phone` | string | |

---

## Domain: Permissions & Roles (Spatie)

### `permissions`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | kebab-case, e.g. `create service-orders` |
| `category` | string | grouping label |
| `description` | string | |
| `guard_name` | string | always `web` |

### `roles`
Standard Spatie roles. Teams feature **disabled**.

### Pivots: `model_has_permissions`, `model_has_roles`, `role_has_permissions`
Standard Spatie many-to-many pivot tables.

---

## Domain: Media (Spatie Media Library)

### `media`
Standard Spatie Media Library schema. Used by: `Customer` (logo, files), `Budget` (invoice_document, survey_images, budget_files), `BudgetPayment` (payment_proofs), `Ticket` (ticket_evidence), `TicketTask` (task_evidence), `Technician` (photo, tax_file), `TechnicianBankAccount` (bank_qr), `TechnicianPayment` (proof), `Deposit` (voucher).

---

## Domain: Customers

### `customers`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `type` | string | `customer` or `prospect` (default: `customer`) |
| `name` | string | short/display name |
| `business_name` | string | legal/business name |
| `rfc` | string(20) | Mexican tax ID |
| `payment_condition` | string | |
| `payment_method` | string | |
| `invoice_usage` | string | CFDI usage code |
| `currency` | string(3) | usually `MXN` |
| `payment_days` | unsignedSmallInt | nullable, credit days |
| `is_active` | boolean | |

**Relationships:** `hasMany(CustomerBranch)`, `hasMany(CustomerContact)`, `hasMany(Ticket)`

### `customer_branches`
| Column | Type | Key |
|--------|------|-----|
| `id` | bigint PK | |
| `customer_id` | FK → customers.id CASCADE | |
| `country` | string(100) | |
| `region` | string(100) | state/region |
| `city` | string(100) | |
| `unit` | string(255) | building/unit/suite |
| `branch_name` | string(255) | |

### `customer_contacts`
| Column | Type | Key |
|--------|------|-----|
| `id` | bigint PK | |
| `customer_id` | FK → customers.id CASCADE | |
| `name` | string | |
| `email` | string | |
| `phone` | string | |
| `position` | string | |

### `customer_branch_contact` (pivot)
Many-to-many between branches and contacts.
| Column | Type |
|--------|------|
| `customer_branch_id` | FK → customer_branches.id |
| `customer_contact_id` | FK → customer_contacts.id |

---

## Domain: Service Types

### `service_types`
Simple taxonomy for ticket classification.
| Column | Type |
|--------|------|
| `id` | bigint PK |
| `name` | string, unique |
| `is_active` | boolean, default true |

---

## Domain: Tickets

### `tickets`
The central work-order entity.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `customer_id` | FK → customers.id CASCADE | |
| `customer_contact_id` | FK → customer_contacts.id CASCADE | |
| `customer_branch_id` | FK → customer_branches.id SET NULL | nullable |
| `seller_id` | FK → users.id SET NULL | nullable, the salesperson |
| `name` | string | ticket/project name |
| `service_type` | string | |
| `report_number` | string | nullable |
| `duration` | string | nullable |
| `technicians` | json | array of lead technician IDs |
| `assistant_technicians` | json | array of assistant technician IDs |
| `status` | string | Borrador → Levantamiento → Catálogo → Proceso de ejecución → Ejecutado → Facturado → Pagado (also: Cancelado, Perdido) |
| `priority` | string | Baja, Media, Alta, Urgente |
| `scheduled_start` | date | nullable |
| `scheduled_end` | date | nullable |
| `instructions` | text | nullable |
| `important_note` | string(500) | nullable, highlighted note |

**Computed:** `progress` (task completion %), `folio` (#ID-REGION-COUNTRY format)  
**Relationships:** `hasOne(Budget)`, `hasMany(TicketTask)`, `hasOne(WorkAcceptanceReport)`, `hasOne(FieldWorkSchedule)`
**Auto-events:** On status change → dispatches `ticketNeedsCatalog` (status=Catálogo) or `ticketNeedsInvoice` (status=Finalizado)

### `ticket_tasks`
Individual tasks within a ticket.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `ticket_id` | FK → tickets.id CASCADE | |
| `user_id` | FK → users.id | nullable, assigned technician |
| `name` | string | |
| `description` | text | nullable |
| `status` | string | Pendiente, En proceso, Completada |
| `technician_notes` | text | nullable |
| `start_date` | datetime | nullable |
| `due_date` | datetime | nullable |
| `completed_at` | datetime | nullable |

---

## Domain: Task Templates

### `task_templates`
Reusable task checklist templates.

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `name` | string |
| `description` | text, nullable |
| `is_active` | boolean |

### `task_template_items`
Line items within a template.
| Column | Type |
|--------|------|
| `id` | bigint PK |
| `task_template_id` | FK → task_templates.id CASCADE |
| `name` | string |
| `description` | text, nullable |

---

## Domain: Budgets

### `budgets`
Financial proposal linked to a ticket (1:1).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `ticket_id` | FK → tickets.id CASCADE | one budget per ticket |
| `status` | string | Borrador, Enviado al cliente, Aprobado, Rechazado, Facturado |
| `description` | text | nullable |
| `currency` | string(3) | MXN or USD |
| `exchange_rate` | decimal(10,4) | default 1 |
| `invoice_date` | date | nullable |
| `invoice_number` | string | nullable |
| `user_id` | FK → users.id | responsible seller |

**Computed:** `total_cost`, `total_paid`, `balance_due`, `total_catalog_cost`

### `budget_concepts`
Line items for budget scope/cost.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `budget_id` | FK → budgets.id CASCADE | |
| `concept` | string | description |
| `amount` | decimal(12,2) | |
| `type` | string | nullable — cost type of the concept: `labor` (mano de obra) or `material` (materiales). Concepts registered before the feature stay `NULL` (the auto-assigned `material` was cleared when the column became nullable, migration `2026_09_18_000001`) |
| `paid_to_technician` | boolean | default false — marks this concept as payable to external tech |
| `payment_date` | date | nullable |

### `budget_payments`
Client payment records.

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `budget_id` | FK → budgets.id CASCADE |
| `amount` | decimal(12,2) |
| `payment_date` | date |
| `reference` | string, nullable |
| `payment_method` | string, nullable |

### `budget_catalogs`
Versioned cost catalog for a budget.

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `budget_id` | FK → budgets.id CASCADE |
| `version` | integer, default 1 |
| `subtotal` | decimal(12,2) |
| `iva` | decimal(12,2) |
| `total` | decimal(12,2) |
| `non_installation_labor` | decimal(12,2) — Empeño Fácil 12% overhead |
| `labor_utility` | decimal(12,2) — Empeño Fácil 18% utility |

### `budget_catalog_items`
Line items in a cost catalog.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `budget_catalog_id` | FK → budget_catalogs.id CASCADE | |
| `type` | string | `material` or `labor` |
| `description` | string | |
| `unit` | string | |
| `technician` | string | nullable, for labor items |
| `hours` | decimal(10,2) | nullable |
| `rate` | decimal(12,2) | nullable |
| `quantity` | decimal(10,2) | |
| `unit_price` | decimal(12,2) | |
| `total` | decimal(12,2) | |

---

## Domain: Technicians

### `technicians`
External/internal technician profiles.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK → users.id CASCADE | |
| `phone` | string | nullable |
| `secondary_phone` | string | nullable |
| `is_internal` | boolean | default false |
| `state` | string | nullable |
| `city` | string | nullable |
| `colony` | string | nullable |
| `zip_code` | string | nullable |
| `coverage_radius_km` | integer | default 10 |
| `specialties` | json | array from 20 predefined options |
| `level` | string(30) | Encargado or Auxiliar/Ayudante |
| `legal_name` | string | nullable |
| `rfc` | string | nullable |
| `bank_name` | string | nullable (legacy, prefer bank_accounts) |
| `bank_account` | string | nullable (legacy) |
| `clabe` | string | nullable (legacy) |
| `status` | enum | Activo, Inactivo, En revisión, Vetado |
| `rating_avg` | decimal(3,2) | default 0.00 |
| `internal_notes` | text | nullable |

### `technician_bank_accounts`
Multiple bank accounts per technician.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `technician_id` | FK → technicians.id CASCADE | |
| `bank_name` | string | nullable |
| `account_number` | string | nullable |
| `card_number` | string | nullable |
| `clabe` | string | nullable |
| `branch_number` | string | nullable |
| `is_favorite` | boolean | default false, one favorite per technician |

### `technician_payments`
Payments made to technicians for completed work.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `budget_id` | FK → budgets.id CASCADE | |
| `user_id` | FK → users.id | technician receiving payment |
| `amount` | decimal(10,2) | |
| `payment_date` | date | |
| `payment_method` | string | nullable |
| `reference` | string | nullable |
| `notes` | text | nullable |

---

## Domain: Calendar

### `calendars`
Shared calendar events.

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `user_id` | FK → users.id (creator) |
| `type` | string (Reunión, Tarea, Llamada, Recordatorio, Evento) |
| `title` | string |
| `description` | text, nullable |
| `start_time` | datetime |
| `end_time` | datetime |
| `is_completed` | boolean |

### `calendar_participants` (pivot)
Many-to-many calendar ↔ users with `status` and `rejection_reason` pivot columns.

### `field_work_schedules`
Field work scheduling for service tickets (1:1 with tickets).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `ticket_id` | FK → tickets.id CASCADE | **unique** — one schedule per ticket |
| `user_id` | FK → users.id | Creator |
| `start_time` | datetime | |
| `end_time` | datetime | |
| `color` | string(20) | hex color, default `#409EFF` |
| `notes` | text | nullable |

---

## Domain: Deposits

### `deposits`
Cash deposit tracking for technician payments.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `technician_id` | FK → technicians.id | |
| `technician_bank_account_id` | FK → technician_bank_accounts.id | |
| `ticket_id` | FK → tickets.id | nullable |
| `budget_id` | FK → budgets.id | nullable, auto-derived |
| `deposit_type_id` | FK → deposit_types.id | |
| `amount` | decimal(10,2) | |
| `shift` | string | matutino or vespertino |
| `scheduled_date` | date | |
| `status` | string | pending → approved → completed |
| `created_by` | FK → users.id | |
| `approved_by` | FK → users.id | nullable |
| `approved_at` | datetime | nullable |
| `completed_at` | datetime | nullable |
| `commission_amount` | decimal(10,2) | nullable |
| `technician_payment_id` | FK → technician_payments.id | nullable, linked on completion |
| `notes` | text | nullable |

### `deposit_types`
| Column | Type |
|--------|------|
| `id` | bigint PK |
| `name` | string |
| `is_active` | boolean |

---

## Domain: Notifications

### `notifications`
Laravel standard notifications table.

### `notification_settings`
Per-user toggles for notification types.

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `notification_type` | string (ticket.needs-catalog, catalog.created, catalog.approved, ticket.needs-invoice, invoice.overdue, deposit.pending-approval) |
| `user_id` | FK → users.id |
| `is_active` | boolean |

---

## Domain: Work Acceptance Reports

### `work_acceptance_reports`
Digital "Acta de recepción" signed by branch managers.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `ticket_id` | FK → tickets.id CASCADE (unique) | One report per ticket |
| `report_date` | date | Auto-set to current date |
| `work_description` | text | nullable — filled manually by technician |
| `on_site_start` | datetime | nullable — technician-entered |
| `on_site_end` | datetime | nullable — technician-entered |
| `technician_comments` | text | nullable — technician-entered |
| `client_comments` | text | nullable — entered at signing |
| `manager_name` | string | nullable |
| `signature_data` | longText | nullable — **Legacy.** Base64 PNG, nullified after migration |
| `signature_path` | string | nullable — **New.** Relative path to PNG in `storage/app/public/signatures/` |
| `signatory_name` | string | nullable |
| `signed_at` | timestamp | nullable — auto-set on signature |
| `is_signed` | boolean | default `false` — locks document when true |
| `created_by` | FK → users.id | |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `belongsTo(Ticket)`, `belongsTo(User, created_by)`

---

## Domain: Payroll & HR (Recursos Humanos)

Created by migrations `2026_09_19_000001` … `000015` (see `docs/context/16-module-payroll.md` for business rules).

### `payroll_settings` (singleton)
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `period_type` | string | **Legacy**: periods are always weekly (Monday–Sunday) and the settings form no longer edits this column |
| `period_anchor_date` | date | **Legacy**: unused, the weeks follow the calendar |
| `late_tolerance_minutes` | int | Default late tolerance |
| `late_discount_mode` | string | `track_only` / `deduct_minutes` |
| `overtime_double_multiplier` / `overtime_triple_multiplier` | decimal | Legacy (unused): overtime is paid at the normal rate |
| `overtime_weekly_threshold_hours` | decimal | Legacy (unused) |
| `holiday_worked_extra_multiplier` | decimal | Default 2 |
| `vacation_min_days_to_request` | int | |
| `vacation_carryover_months` | int | Default 18 |
| `incapacity_paid` / `incapacity_pay_percentage` | bool / int | Default false / 60 |
| `default_daily_hours` | decimal | Default 8. No longer in the settings form: hidden fallback for profiles without an assigned schedule |
| `payroll_expense_category_id` | FK → expense_categories.id | Mirrored expense category |
| `face_recognition_enabled`, `face_match_threshold`, `rekognition_collection_id`, `kiosk_pin_fallback_enabled` | mixed | Facial recognition + kiosk fallback |
| `attendance_capture_retention_months` | int | Default 12 (purged by `payroll:prune-captures`) |
| `remote_geolocation_required` | bool | Default true |
| `updated_by` | FK → users.id | nullable |

### `payroll_profiles`
One per user. `employee_number` (unique, auto `EMP-####`), `hire_date`, `termination_date`, `daily_salary`, `daily_hours` (kept in sync from the assigned schedule by `AssignUserShiftAction`; no longer typed in the forms), `is_payroll_subject`, `is_attendance_subject`, `can_remote_attendance`, `kiosk_pin` (bcrypt) + `kiosk_pin_lookup` (HMAC fingerprint for the kiosk search; migration `2026_09_22_000001`), `notes`.

### `attendance_devices`
Authorized kiosk devices: `name`, `location`, `token_hash` (sha256, unique), registered by/at, `last_seen_at`/`last_seen_ip`/`user_agent`, `is_active`, revoked by/at.

### `attendance_logs`
Punches: `user_id`, `attendance_device_id` (nullable), `type` (check_in, lunch_start, lunch_end, break_start, break_end, check_out), `punched_at`, `source` (kiosk/remote/manual), `identifier_method` (face/pin/manual), `face_similarity`, `latitude`/`longitude`/`location_accuracy`, `ip`, `user_agent`, audit `edited_by`/`edited_at`/`edit_reason`. Evidence photo in media collection `capture`.

### `attendance_day_overrides`
Sparse per user+date: `late_ignored`, `notes`, `updated_by` (unique user_id + date).

### `shifts`
`name`, `type` (fixed/rotating/flexible), `start_time`, `end_time`, `meal_minutes`, `is_meal_paid`, `days` (ISO 1-7 JSON), `required_daily_hours`, `late_tolerance_minutes` (nullable), `is_active`, `description`.

### `shift_assignments`
`user_id` **or** `department`, `type`, `shift_id`, `rotation` (JSON weekly cycle of shift ids), `start_date`, `end_date`, `notes`.

### `holidays`
`date` (unique), `name`, `year`, `source` (`lft`/`manual`), `is_mandatory`, `apply_extra_pay`, `notes`.

### `incidents`
`user_id`, `type` (absence_justified/absence_unjustified/medical_leave/work_incapacity/permission_paid/permission_unpaid/vacation/other; IMSS incapacities are never paid), `start_date`, `end_date`, `days`, `affects_pay`, `status`, `notes`, `vacation_request_id` (nullable), created/approved audit. Optional `support` attachment.

### `vacation_requests`
`user_id`, `start_date`, `end_date`, `days`, `status` (pending/approved/rejected/cancelled), `reason`, `requested_by`, `reviewed_by`, `reviewed_at`, `review_notes`.

### `payroll_periods`
`type`, `start_date`, `end_date`, `status` (open/closed), `closed_at`, `closed_by` (null = automatic), `reopened_at` (migration `2026_09_22_000002`: a period reopened by hand is never closed automatically again), `total_gross`, `total_deductions`, `total_net`, `expense_id`, `notes`. Periods are weekly (Monday–Sunday) and no two rows can ever share dates.

### `payroll_adjustments`
`payroll_period_id`, `user_id`, `type` (earning/deduction), `concept`, `amount`, `notes`, `created_by`.

### `payslips` (+ `payslip_lines`, `payslip_days`)
Frozen closing snapshot: profile fields (`employee_number`, `department`, `position`, `hire_date`, `daily_salary`, `daily_hours`), totals (days paid/unpaid, late minutes/discount, overtime 2x/3x, holidays, vacations, incapacity, adjustments, gross/deductions/net), `generated_at`/`generated_by`. Lines store concept/type/quantity/unit_rate/amount/source; days store date/status/first_in/lunch/last_out/worked/late/overtime minutes.

### `face_enrollments`
`user_id`, `collection_id`, `face_id`, `external_image_id`, `status` (active/failed/removed), `quality`, `enrolled_by`, `enrolled_at`.

### `expenses` (payroll additions)
`payroll_period_id` (FK → payroll_periods.id, unique, nullOnDelete — the mirrored period expense) and `created_by` became nullable (command-generated).

---

## Cross-domain foreign key summary

```
users.id ──▶ employees.user_id, technicians.user_id, tickets.seller_id,
            ticket_tasks.user_id, budgets.user_id, calendars.user_id,
            calendar_participants.user_id, technician_payments.user_id,
            deposits.created_by, deposits.approved_by, notification_settings.user_id,
            work_acceptance_reports.created_by, field_work_schedules.user_id,
            payroll_profiles.user_id, attendance_logs.user_id, attendance_day_overrides.user_id,
            shift_assignments.user_id, incidents.user_id, vacation_requests.user_id,
            payroll_adjustments.user_id, payslips.user_id, face_enrollments.user_id,
            payroll_settings.updated_by, attendance_devices.registered_by

payroll_periods.id ──▶ payroll_adjustments.payroll_period_id,
                      payslips.payroll_period_id, expenses.payroll_period_id

shifts.id ──▶ shift_assignments.shift_id

attendance_devices.id ──▶ attendance_logs.attendance_device_id

vacation_requests.id ──▶ incidents.vacation_request_id

customers.id ──▶ customer_branches.customer_id, customer_contacts.customer_id,
                 tickets.customer_id

customer_branches.id ──▶ customer_branch_contact.customer_branch_id,
                         tickets.customer_branch_id

customer_contacts.id ──▶ customer_branch_contact.customer_contact_id,
                         tickets.customer_contact_id

tickets.id ──▶ budgets.ticket_id, ticket_tasks.ticket_id, deposits.ticket_id,
            work_acceptance_reports.ticket_id, field_work_schedules.ticket_id

budgets.id ──▶ budget_concepts.budget_id, budget_payments.budget_id,
              budget_catalogs.budget_id, technician_payments.budget_id,
              deposits.budget_id

budget_catalogs.id ──▶ budget_catalog_items.budget_catalog_id

technicians.id ──▶ technician_bank_accounts.technician_id, deposits.technician_id

deposit_types.id ──▶ deposits.deposit_type_id

task_templates.id ──▶ task_template_items.task_template_id
```
