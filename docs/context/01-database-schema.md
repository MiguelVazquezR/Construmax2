# Construmax2 ERP — 01: Database Schema

> Summarizes all ~31 tables grouped by domain. Focuses on relationships and business-meaningful columns.

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
| `notification_type` | string (ticket.needs-catalog, catalog.created, ticket.needs-invoice, invoice.overdue, deposit.pending-approval) |
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
| `signature_data` | longText | nullable — base64 PNG from canvas |
| `signatory_name` | string | nullable |
| `signed_at` | timestamp | nullable — auto-set on signature |
| `is_signed` | boolean | default `false` — locks document when true |
| `created_by` | FK → users.id | |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `belongsTo(Ticket)`, `belongsTo(User, created_by)`

---

## Cross-domain foreign key summary

```
users.id ──▶ employees.user_id, technicians.user_id, tickets.seller_id,
            ticket_tasks.user_id, budgets.user_id, calendars.user_id,
            calendar_participants.user_id, technician_payments.user_id,
            deposits.created_by, deposits.approved_by, notification_settings.user_id,
            work_acceptance_reports.created_by, field_work_schedules.user_id

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
