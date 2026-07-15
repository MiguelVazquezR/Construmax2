# Construmax2 ERP — Project Context

> **Generated:** 2026-07-14  
> **Purpose:** Full architectural reference for the codebase, to be used as context when designing new modules (e.g., "Depósitos" / Deposits).

---

## 1. Development Stack

| Layer | Technology | Version / Details |
|-------|-----------|-------------------|
| **PHP** | PHP | `^8.2` (composer.json) |
| **Framework** | Laravel | `^12.0` |
| **Auth scaffolding** | Laravel Jetstream | `^5.4` (with Inertia stack) |
| **API tokens** | Laravel Sanctum | `^4.0` |
| **Frontend framework** | Vue 3 | `^3.3.13` (Composition API, `<script setup>`) |
| **Frontend bridge** | Inertia.js | `^2.0` (server: `inertiajs/inertia-laravel`, client: `@inertiajs/vue3`) |
| **UI library** | Element Plus | `^2.13.2` |
| **CSS framework** | Tailwind CSS | `^3.4.0` (with `@tailwindcss/forms`, `@tailwindcss/typography`) |
| **Charts** | ApexCharts + ECharts | `vue3-apexcharts ^1.10.0`, `vue-echarts ^8.0.1` |
| **Drag & drop** | vuedraggable | `^4.1.0` |
| **Build tool** | Vite | `^7.0.7` with `laravel-vite-plugin ^2.0.0` |
| **Backend router** | Ziggy | `tightenco/ziggy ^2.0` (route names exposed to JS) |
| **Roles & permissions** | Spatie Laravel Permission | `^6.24` |
| **Media handling** | Spatie Laravel MediaLibrary | `^11.17` |
| **Dev tools** | Laravel Pint (`^1.24`), Laravel Pail (`^1.2.2`), Laravel Sail (`^1.41`) | 
| **Testing** | PHPUnit | `^11.5.3` |
| **Database (dev default)** | SQLite | `database/database.sqlite` |
| **Database (production)** | MySQL / MariaDB | `utf8mb4_unicode_ci` |
| **Queue driver** | Database | `QUEUE_CONNECTION=database` |
| **Cache driver** | Database | `CACHE_STORE=database` |
| **Session driver** | Database | `SESSION_DRIVER=database` |
| **Broadcast** | Log | `BROADCAST_CONNECTION=log` |
| **Mail** | Log | `MAIL_MAILER=log` |

---

## 2. Installed Packages

### 2.1 Composer — `require`

| Package | Version | Purpose |
|---------|---------|---------|
| `php` | `^8.2` | Runtime |
| `laravel/framework` | `^12.0` | Core framework |
| `laravel/jetstream` | `^5.4` | Auth scaffolding (Inertia stack) |
| `laravel/sanctum` | `^4.0` | API token & SPA authentication |
| `laravel/tinker` | `^2.10.1` | Interactive REPL |
| `inertiajs/inertia-laravel` | `^2.0` | Server-side Inertia adapter |
| `spatie/laravel-medialibrary` | `^11.17` | File uploads, media associations, conversions |
| `spatie/laravel-permission` | `^6.24` | Roles & permissions (RBAC) |
| `tightenco/ziggy` | `^2.0` | Exposes Laravel named routes to JavaScript |

### 2.2 Composer — `require-dev`

| Package | Version | Purpose |
|---------|---------|---------|
| `fakerphp/faker` | `^1.23` | Seed data generation |
| `laravel/pail` | `^1.2.2` | Log tailing |
| `laravel/pint` | `^1.24` | PHP code style fixer |
| `laravel/sail` | `^1.41` | Docker dev environment |
| `mockery/mockery` | `^1.6` | Mocking framework |
| `nunomaduro/collision` | `^8.6` | Enhanced error reporting |
| `phpunit/phpunit` | `^11.5.3` | Testing framework |

### 2.3 NPM — `dependencies`

| Package | Version | Purpose |
|---------|---------|---------|
| `@element-plus/icons-vue` | `^2.3.2` | Icon set for Element Plus |
| `apexcharts` | `^5.3.6` | Charting library (via vue3-apexcharts) |
| `echarts` | `^6.1.0` | Charting library (via vue-echarts) |
| `element-plus` | `^2.13.2` | Vue 3 UI component library |
| `lodash` | `^4.17.23` | Utility functions |
| `vue-echarts` | `^8.0.1` | ECharts Vue wrapper |
| `vue3-apexcharts` | `^1.10.0` | ApexCharts Vue wrapper |
| `vuedraggable` | `^4.1.0` | Drag-and-drop for Vue 3 |

### 2.4 NPM — `devDependencies`

| Package | Version | Purpose |
|---------|---------|---------|
| `@inertiajs/vue3` | `^2.0` | Inertia client for Vue 3 |
| `@tailwindcss/forms` | `^0.5.7` | Tailwind form reset plugin |
| `@tailwindcss/typography` | `^0.5.10` | Tailwind prose plugin |
| `@tailwindcss/vite` | `^4.0.0` | Tailwind Vite integration |
| `@vitejs/plugin-vue` | `^6.0.1` | Vue SFC Vite plugin |
| `autoprefixer` | `^10.4.16` | CSS vendor prefixes |
| `axios` | `^1.11.0` | HTTP client |
| `concurrently` | `^9.0.1` | Run multiple commands |
| `laravel-vite-plugin` | `^2.0.0` | Laravel Vite integration |
| `postcss` | `^8.4.32` | CSS post-processing |
| `tailwindcss` | `^3.4.0` | Utility-first CSS framework |
| `unplugin-auto-import` | `^21.0.0` | Auto-import Vue/Inertia APIs |
| `unplugin-vue-components` | `^31.0.0` | Auto-import Element Plus components |
| `vite` | `^7.0.7` | Build tool |
| `vue` | `^3.3.13` | Frontend framework |

---

## 3. Folder Structure

### 3.1 `app/`

```
app/
├── Actions/
│   ├── Fortify/
│   │   ├── CreateNewUser.php
│   │   ├── PasswordValidationRules.php
│   │   ├── ResetUserPassword.php
│   │   ├── UpdateUserPassword.php
│   │   └── UpdateUserProfileInformation.php
│   ├── Invoices/
│   │   └── UploadInvoiceAction.php
│   ├── Jetstream/
│   │   └── DeleteUser.php
│   └── Notifications/
│       └── DispatchNotificationAction.php
├── Console/
│   └── Commands/
├── Http/
│   ├── Controllers/
│   │   ├── Config/
│   │   │   └── NotificationController.php
│   │   ├── BudgetController.php
│   │   ├── CalendarController.php
│   │   ├── Controller.php (base)
│   │   ├── CostController.php
│   │   ├── CustomerController.php
│   │   ├── DashboardController.php
│   │   ├── InvoiceController.php
│   │   ├── RolePermissionController.php
│   │   ├── ServiceTypeController.php
│   │   ├── TaskTemplateController.php
│   │   ├── TechnicianController.php
│   │   ├── TicketAnalyticsController.php
│   │   ├── TicketController.php
│   │   ├── TicketTaskController.php
│   │   ├── TutorialController.php
│   │   └── UserController.php
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php
│   └── Requests/
│       └── Invoices/
│           └── UploadInvoiceRequest.php
├── Models/
│   ├── Budget.php
│   ├── BudgetCatalog.php
│   ├── BudgetCatalogItem.php
│   ├── BudgetConcept.php
│   ├── BudgetPayment.php
│   ├── Calendar.php
│   ├── Customer.php
│   ├── CustomerBranch.php
│   ├── CustomerContact.php
│   ├── Employee.php
│   ├── NotificationSetting.php
│   ├── ServiceType.php
│   ├── TaskTemplate.php
│   ├── TaskTemplateItem.php
│   ├── Technician.php
│   ├── TechnicianBankAccount.php
│   ├── TechnicianPayment.php
│   ├── Ticket.php
│   ├── TicketTask.php
│   └── User.php
├── Notifications/
│   ├── CatalogCreated.php
│   ├── InvoiceOverdue.php
│   ├── TicketNeedsCatalog.php
│   └── TicketNeedsInvoice.php
├── Providers/
│   ├── AppServiceProvider.php
│   ├── FortifyServiceProvider.php
│   └── JetstreamServiceProvider.php
└── Services/
    ├── Costs/
    │   └── CostService.php
    ├── Invoices/
    │   └── InvoiceService.php
    ├── Media/
    │   └── ImageOptimizerService.php
    └── Notifications/
        └── NotificationService.php
```

### 3.2 `database/`

```
database/
├── factories/
├── migrations/               (31 migrations, see §5)
├── seeders/
```

### 3.3 `routes/`

```
routes/
├── api.php                   (minimal: /user endpoint)
├── console.php
├── web.php                   (entry point, imports sub-files)
└── web/
    ├── budgets.php
    ├── calendar.php
    ├── costs.php
    ├── crm.php               (redirect only)
    ├── customers.php
    ├── invoices.php
    ├── notifications.php
    ├── roles-permissions.php
    ├── service-types.php
    ├── technicians.php
    ├── tickets.php
    ├── tutorials.php
    └── users.php
```

### 3.4 `resources/`

```
resources/
├── css/
├── js/
│   ├── Pages/
│   │   ├── API/
│   │   ├── Auth/             (Jetstream auth pages)
│   │   ├── Budgets/          (Index, Create, Edit, Show + Partials/)
│   │   ├── Calendar/
│   │   ├── Config/Notifications/
│   │   ├── Costs/            (Index, Show, Print, PrintEmpenoFacil)
│   │   ├── Customers/        (Index, Create, Edit, Show + Partials/)
│   │   ├── Invoices/         (Index + Partials/)
│   │   ├── Profile/          (Jetstream profile pages + Partials/)
│   │   ├── RolePermissions/
│   │   ├── Technicians/      (Index, Create, Edit, Show + Partials/)
│   │   ├── Tickets/          (Index, Create, Edit, Show + Partials/)
│   │   ├── Tutorials/
│   │   ├── Users/            (Index, Create, Edit, Show)
│   │   ├── Dashboard.vue
│   │   ├── Error.vue
│   │   ├── TicketsDashboard.vue
│   │   └── Welcome.vue
│   └── ... (app.js, etc.)
├── markdown/
└── views/                    (only `app.blade.php` — Inertia root)
```

### 3.5 Architecture Pattern

This project follows a **layered architecture** with clear separation:

```
HTTP Request
  → Controller       (thin — delegates immediately)
  → Action           (single-use case orchestrator)
  → Service          (reusable business logic)
  → Model            (rich domain logic, scopes, relationships, mutators)
  → FormRequest      (all validation lives here — though sparsely used)
```

Key observations:
- **Controllers are thin**: they receive requests, call actions/services, return Inertia responses.
- **Actions** exist for specific use cases (`UploadInvoiceAction`, `DispatchNotificationAction`) but are not universally applied (many controllers still contain business logic directly).
- **Services** cover reusable logic for Costs, Invoices, Notifications, and Media.
- **Form Requests** are the standard pattern but so far only 1 exists (`UploadInvoiceRequest`); validation is mostly inline in controllers via `$request->validate()`.
- **No Repository pattern** is used — models are queried directly.
- **No DTOs** are used.

---

## 4. Models

### 4.1 Complete Model Registry

| Model | Table | Traits | Fillable | Key Relationships |
|-------|-------|--------|----------|-------------------|
| **User** | `users` | `HasApiTokens`, `HasFactory`, `HasProfilePhoto`, `Notifiable`, `TwoFactorAuthenticatable`, `HasRoles` | `name`, `email`, `password`, `is_active` | `hasOne Employee`, `hasOne Technician`, `hasMany Ticket` (as seller) |
| **Employee** | `employees` | `HasFactory` | `user_id`, `department`, `position`, `phone` | `belongsTo User` |
| **Customer** | `customers` | `HasFactory`, `InteractsWithMedia` | `type`, `name`, `business_name`, `rfc`, `payment_condition`, `payment_method`, `invoice_usage`, `currency`, `payment_days`, `is_active` | `hasMany Branch`, `hasMany Contact`, `hasMany Ticket` |
| **CustomerBranch** | `customer_branches` | `HasFactory` | `customer_id`, `country`, `region`, `city`, `unit`, `branch_name` | `belongsTo Customer`, `belongsToMany Contact` |
| **CustomerContact** | `customer_contacts` | `HasFactory` | `customer_id`, `name`, `email`, `phone`, `position` | `belongsTo Customer`, `belongsToMany Branch` |
| **Ticket** | `tickets` | `HasFactory`, `InteractsWithMedia` | `customer_id`, `customer_contact_id`, `customer_branch_id`, `seller_id`, `name`, `service_type`, `report_number`, `duration`, `technicians` (JSON), `assistant_technicians` (JSON), `status`, `priority`, `scheduled_start`, `scheduled_end`, `instructions`, `important_note` | `belongsTo Customer`, `belongsTo Contact`, `belongsTo Branch`, `belongsTo User` (seller), `hasOne Budget`, `hasMany TicketTask` |
| **TicketTask** | `ticket_tasks` | `HasFactory`, `InteractsWithMedia` | `ticket_id`, `user_id`, `name`, `description`, `status`, `start_date`, `due_date`, `completed_at`, `technician_notes` | `belongsTo Ticket`, `belongsTo User` (assignee) |
| **Budget** | `budgets` | `HasFactory`, `InteractsWithMedia` | `ticket_id`, `status`, `description`, `currency`, `exchange_rate`, `user_id`, `invoice_date`, `invoice_number` | `belongsTo Ticket`, `hasOneThrough Customer`, `belongsTo User` (responsible), `hasMany BudgetConcept`, `hasMany BudgetPayment`, `hasMany TechnicianPayment`, `hasMany BudgetCatalog`, `hasOne BudgetCatalog` (latest) |
| **BudgetConcept** | `budget_concepts` | `HasFactory` | `budget_id`, `concept`, `amount`, `paid_to_technician`, `payment_date` | None explicitly defined (belongsTo Budget implicit) |
| **BudgetPayment** | `budget_payments` | `HasFactory`, `InteractsWithMedia` | `budget_id`, `amount`, `payment_date`, `reference`, `payment_method` | `belongsTo Budget` |
| **BudgetCatalog** | `budget_catalogs` | `HasFactory` | `budget_id`, `version`, `subtotal`, `iva`, `total`, `non_installation_labor`, `labor_utility` | `belongsTo Budget`, `hasMany BudgetCatalogItem` |
| **BudgetCatalogItem** | `budget_catalog_items` | `HasFactory` | `budget_catalog_id`, `type`, `description`, `unit`, `technician`, `hours`, `rate`, `quantity`, `unit_price`, `total` | `belongsTo BudgetCatalog` |
| **Technician** | `technicians` | `HasFactory`, `InteractsWithMedia` | `user_id`, `phone`, `secondary_phone`, `is_internal`, `state`, `city`, `colony`, `zip_code`, `coverage_radius_km`, `specialties` (JSON), `level`, `legal_name`, `rfc`, `bank_name`, `bank_account`, `clabe`, `status`, `rating_avg`, `internal_notes` | `belongsTo User`, `hasMany TechnicianBankAccount` |
| **TechnicianBankAccount** | `technician_bank_accounts` | `InteractsWithMedia` | `technician_id`, `account_number`, `card_number`, `clabe`, `branch_number`, `is_favorite` | `belongsTo Technician` |
| **TechnicianPayment** | `technician_payments` | `HasFactory`, `InteractsWithMedia` | `budget_id`, `user_id`, `amount`, `payment_date`, `payment_method`, `reference`, `notes` | `belongsTo Budget`, `belongsTo User` (technician) |
| **Calendar** | `calendars` | `HasFactory` | `user_id`, `type`, `title`, `description`, `start_time`, `end_time`, `is_completed` | `belongsTo User` (creator), `belongsToMany User` (participants via `calendar_participants`) |
| **ServiceType** | `service_types` | `HasFactory` | `name`, `is_active` | None |
| **TaskTemplate** | `task_templates` | `HasFactory` | `name`, `description`, `is_active` | `hasMany TaskTemplateItem` |
| **TaskTemplateItem** | `task_template_items` | `HasFactory` | `task_template_id`, `name`, `description` | `belongsTo TaskTemplate` |
| **NotificationSetting** | `notification_settings` | `HasFactory` | `notification_type`, `user_id`, `is_active` | `belongsTo User` |

### 4.2 Notable Custom Features

- **Ticket**: Auto-generates a `folio` accessor (`#ID-REGION-COUNTRY`). Computes `progress` (%). Has `booted()` hook that fires notifications on status change (`Catálogo` → `ticketNeedsCatalog`, `Finalizado` → `ticketNeedsInvoice`). Has `updateStatusBasedOnTasks()` method.
- **Budget**: Computes `total_cost`, `total_paid`, `balance_due`, `total_catalog_cost`. Has rich `scopeFilter()` with search, status, user_id, branch.
- **Technician**: Has constant arrays for `LEVELS` and `SPECIALTIES` (20 specialties). Has `scopeWhereInvolved()` to find tickets where a technician participated. Has `scopeFilter()` with search, specialty (including Unicode-escaped JSON), state.
- **Customer**: `scopeFilter()` supports search (name, business_name, rfc), region, contact.
- **User**: Has `scopeFilter()` for name/email search.

---

## 5. Migrations

All 31 migrations in chronological order:

| # | Migration File | Table(s) | Purpose |
|---|---------------|----------|---------|
| 1 | `0001_01_01_000000` | `users`, `password_reset_tokens`, `sessions` | Core Laravel tables |
| 2 | `0001_01_01_000001` | `cache`, `cache_locks` | Cache tables |
| 3 | `0001_01_01_000002` | `jobs`, `job_batches`, `failed_jobs` | Queue tables |
| 4 | `2026_02_05_235127` | `users` (alter) | Add `two_factor_columns` (2FA) |
| 5 | `2026_02_05_235226` | `personal_access_tokens` | Sanctum tokens |
| 6 | `2026_02_06_003519` | `employees` | Employee profiles (user_id FK, department, position, phone) |
| 7 | `2026_02_06_095942` | `permissions`, `roles`, `model_has_*`, `role_has_permissions` | Spatie Permission tables |
| 8 | `2026_02_06_101308` | `media` | Spatie MediaLibrary polymorphic table |
| 9 | `2026_02_06_121216` | `customers`, `customer_branches`, `customer_contacts`, `customer_branch_contact` | Customer CRM tables |
| 10 | `2026_02_06_170334` | `tickets`, `ticket_tasks` | Core operational tables |
| 11 | `2026_02_06_170335` | `budgets`, `budget_concepts`, `budget_payments` | Budget/quoting tables |
| 12 | `2026_02_06_214607` | `calendars`, `calendar_participants` | Calendar & event scheduling |
| 13 | `2026_02_11_185729` | `technicians` | Technician profiles with JSON specialties |
| 14 | `2026_02_14_195643` | `technician_payments` | Payments to technicians (budget_id, user_id FKs) |
| 15 | `2026_05_21_122316` | `task_templates`, `task_template_items` | Reusable task templates |
| 16 | `2026_05_23_114402` | `budget_catalogs`, `budget_catalog_items` | Versioned cost catalogs |
| 17 | `2026_05_24_000001` | `tickets` (alter) | Add `seller_id` FK to tickets |
| 18 | `2026_06_05_000001` | `customer_branches` (alter) | Add `city` column |
| 19 | `2026_06_05_000002` | `budget_concepts` (alter) | Add `paid_to_technician`, `payment_date` |
| 20 | `2026_06_05_000003` | `ticket_tasks` (alter) | Add `technician_notes` |
| 21 | `2026_06_06_112406` | `notifications` | Laravel notifications table |
| 22 | `2026_06_06_112416` | `notification_settings` | User notification preferences |
| 23 | `2026_06_17_000001` | `technicians` (alter) | Add `level` column |
| 24 | `2026_06_17_000002` | `tickets` (alter) | Add `assistant_technicians` JSON |
| 25 | `2026_06_26_000001` | `service_types` | Service type catalog |
| 26 | `2026_06_26_000001` | `budget_catalog_items` (alter) | Add `type`, `technician`, `hours`, `rate` (Empeño Fácil) |
| 27 | `2026_06_26_000002` | `budget_catalogs` (alter) | Add `non_installation_labor`, `labor_utility` |
| 28 | `2026_07_10_141122` | `tickets` (alter) | Add `report_number` |
| 29 | `2026_07_10_162737` | `customers` (alter) | Add `type` (customer/prospect) |
| 30 | `2026_07_11_095029` | `tickets` (alter) | Add `important_note` |
| 31 | `2026_07_12_092305` | `technician_bank_accounts` | Multiple bank accounts per technician |

### Naming Conventions

- Tables: **snake_case**, English, plural (Laravel default).
- Pivot tables: **singular_singular** (`customer_branch_contact`, `calendar_participants`).
- Foreign keys: **`{model}_id`**, cascade on delete where appropriate.
- No table prefixes.

---

## 6. Controllers

### 6.1 Full Controller Registry

| Controller | Location | Type | Key Dependencies | Notable |
|-----------|----------|------|-----------------|---------|
| **BudgetController** | `app/Http/Controllers/` | Resource + Custom | `ImageOptimizerService` | Handles payments, files, technician payments via custom methods |
| **CalendarController** | `app/Http/Controllers/` | Custom CRUD | None | Also handles `overview()` for badge, `respond()` to invitations, `toggleComplete()` |
| **CostController** | `app/Http/Controllers/` | Custom | `CostService`, `DispatchNotificationAction` | Catalog versioning, print views, Empeño Fácil print |
| **CustomerController** | `app/Http/Controllers/` | Resource + Custom | None | `toggleStatus()`, `deleteMedia()`, `deleteLogo()`, `uploadFiles()`, `quickStoreBranch()`, `convertToCustomer()` |
| **DashboardController** | `app/Http/Controllers/` | Single action | None | Aggregates KPIs: events, tickets, payments, budgets, costs |
| **InvoiceController** | `app/Http/Controllers/` | Custom | `InvoiceService`, `UploadInvoiceAction` | Uses `UploadInvoiceRequest` FormRequest |
| **RolePermissionController** | `app/Http/Controllers/` | Custom | Spatie `Role`, `Permission` models | CRUD for roles and permissions; permissions grouped by category |
| **ServiceTypeController** | `app/Http/Controllers/` | Custom CRUD (JSON API) | None | Returns JSON; all operations via AJAX |
| **TaskTemplateController** | `app/Http/Controllers/` | Custom CRUD (JSON API) | None | Uses `AuthorizesRequests`; returns JSON |
| **TechnicianController** | `app/Http/Controllers/` | Resource + Custom | `ImageOptimizerService` | `quickStore()`, `updateRating()`, `updateStatus()`, bank account management |
| **TicketAnalyticsController** | `app/Http/Controllers/` | Single action | None | Rich analytics with date range, customer, seller filters; KPIs, charts data |
| **TicketController** | `app/Http/Controllers/` | Resource + Custom | `ImageOptimizerService` | `storeFromBudget()`, `updateStatus()`, `updateTechnicians()`, `updateField()`, evidence management |
| **TicketTaskController** | `app/Http/Controllers/` | Custom | `ImageOptimizerService` | Public routes (signed URLs), overlap checking, evidence management |
| **TutorialController** | `app/Http/Controllers/` | Single action | None | Static video catalog; permission-gated |
| **UserController** | `app/Http/Controllers/` | Resource + Custom | None | `toggleStatus()`; creates Employee on store |
| **NotificationController** | `app/Http/Controllers/Config/` | Custom | `NotificationService` | Settings CRUD, fetch/mark-read/delete for bell dropdown |

### 6.2 Validation Approach

- Only **1 Form Request** exists: `UploadInvoiceRequest` (`app/Http/Requests/Invoices/`)
- All other validation is **inline** via `$request->validate()` inside controller methods.
- Controllers that use inline validation include: `CustomerController`, `UserController`, `TicketController`, `TicketTaskController`, `TaskTemplateController`, `CostController`.

---

## 7. Services / Actions

### 7.1 Services

| Service | Namespace | Responsibility |
|---------|-----------|----------------|
| **CostService** | `App\Services\Costs` | Query budgets for cost management; build catalog details with technician resolution |
| **InvoiceService** | `App\Services\Invoices` | Query budgets for invoicing; compute due dates from `payment_days`; gather task evidence |
| **ImageOptimizerService** | `App\Services\Media` | Resize and compress uploaded images (max 1920×1920, quality 75); GD-based |
| **NotificationService** | `App\Services\Notifications` | Get subscribers by type, dispatch notifications, sync user settings |

### 7.2 Actions

| Action | Namespace | Responsibility |
|--------|-----------|----------------|
| **DispatchNotificationAction** | `App\Actions\Notifications` | Fire `TicketNeedsCatalog`, `CatalogCreated`, `TicketNeedsInvoice`, `InvoiceOverdue` notifications |
| **UploadInvoiceAction** | `App\Actions\Invoices` | Update budget invoice fields, sync ticket status, attach invoice file |
| **CreateNewUser** | `App\Actions\Fortify` | Jetstream user registration |
| **UpdateUserPassword** | `App\Actions\Fortify` | Jetstream password update |
| **UpdateUserProfileInformation** | `App\Actions\Fortify` | Jetstream profile update |
| **ResetUserPassword** | `App\Actions\Fortify` | Jetstream password reset |
| **PasswordValidationRules** | `App\Actions\Fortify` | Shared password rules |
| **DeleteUser** | `App\Actions\Jetstream` | Account deletion |

---

## 8. Middlewares

### 8.1 Custom Middlewares

| Middleware | Path | Purpose | Registration |
|-----------|------|---------|-------------|
| **HandleInertiaRequests** | `app/Http/Middleware/` | Shares auth user, permissions, roles, and flash messages with all Inertia views | Global web middleware group (via `bootstrap/app.php` append) |

### 8.2 Middleware Groups (from `bootstrap/app.php`)

- **Web**: `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets` appended.
- **API**: Default Sanctum API middleware.
- **Route-level**: `auth:sanctum`, `verified`, `signed` (for public technician URLs).

---

## 9. Routes

### 9.1 Route Organization

- `routes/web.php` is the entry point. It imports 13 sub-files from `routes/web/`.
- Routes are grouped by auth middleware (`auth:sanctum`, `verified`) in each file.
- Named routes use dot-prefix convention per module.

### 9.2 Module Route Files

| File | Prefix | Name Prefix | Key Routes |
|------|--------|-------------|------------|
| `web/users.php` | `/users` | `users.` | Resource + `toggleStatus` |
| `web/roles-permissions.php` | `/config` | `config.` | CRUD for roles & permissions |
| `web/customers.php` | `/customers` | `customers.` | Resource + media, quick-branch, convert-to-customer |
| `web/budgets.php` | `/budgets` | `budgets.` | Resource + payments, files, technician-payments |
| `web/crm.php` | `/crm` | `crm.` | Redirect to `/tickets/dashboard` |
| `web/tickets.php` | `/tickets`, `/t` | `tickets.`, `tasks.` | Resource + tasks, evidence, templates, public routes |
| `web/service-types.php` | `/service-types` | `service-types.` | CRUD (JSON API) |
| `web/calendar.php` | `/calendar` | `calendar.` | CRUD + overview, respond, toggle-complete |
| `web/technicians.php` | `/technicians` | `technicians.` | Resource + quickStore, rating, status, bank-accounts |
| `web/invoices.php` | `/invoices` | `invoices.` | Index + upload |
| `web/costs.php` | `/costs` | `costs.` | Index, show, print, print-empeno-facil, store-catalog |
| `web/notifications.php` | `/notifications`, `/config/notifications` | `notifications.`, `config.notifications.` | Fetch, mark-read, settings CRUD |
| `web/tutorials.php` | `/tutorials` | `tutorials.` | Index only |

### 9.3 Special Routes

- **Public signed routes**: `/t/job-order/{ticket}/{user}`, `/t/track/{task}/toggle`, `/t/track/{task}/evidence` — allow external technicians to access job orders and submit evidence without authentication.
- **Map proxy**: `/maps/{country}` — proxies GeoJSON map data to avoid CORS issues.
- **Exchange rate proxy**: `/api/exchange-rate` — fetches USD→MXN rate from `open.er-api.com`.
- **Storage fallback**: `/storage/{extra}` — serves files directly when symlinks aren't available (shared hosting).

### 9.4 Route Naming Convention

- All URL segments use **kebab-case**: `service-types`, `task-templates`, `roles-permissions`.
- Named routes use dot notation: `budgets.payments.store`, `technicians.bank-accounts.favorite`.
- Resource routes use Laravel's standard naming.

---

## 10. Authentication & Authorization

### 10.1 Auth System

- **Laravel Jetstream** with the **Inertia** stack (not Livewire).
- Uses **Laravel Fortify** under the hood for backend auth logic.
- **Laravel Sanctum** for API token authentication and SPA auth.
- Features enabled: 2FA, profile photos, browser session management, account deletion.

### 10.2 Roles & Permissions (Spatie)

- Package: `spatie/laravel-permission ^6.24`
- Roles and permissions use Spatie's default tables (`roles`, `permissions`, `model_has_roles`, etc.).
- User model uses `HasRoles` trait.
- Permissions are shared to the frontend via `HandleInertiaRequests` middleware (`auth.permissions`, `auth.roles`).
- **Permission format**: kebab-case (e.g., `tickets.index-all`, `costs.create`, `invoices.upload`, `config.notifications`).
- Permission checks in controllers use `$request->user()->can('...')`; some also use `$this->authorize()` in `TaskTemplateController`.
- Permissions are categorized (visible in `RolePermissionController`).
- User ID 1 is excluded from the user management list (super admin).

### 10.3 Policies

- **No Policy classes** found in `app/Policies/`. Authorization is handled inline via Spatie permission checks.

---

## 11. Conventions & Patterns Observed

### 11.1 Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| **Controllers** | PascalCase, singular suffix | `BudgetController`, `TicketController` |
| **Models** | PascalCase, singular | `Customer`, `BudgetCatalogItem` |
| **Tables** | snake_case, plural | `customer_branches`, `budget_payments` |
| **Foreign keys** | `{singular}_id` | `customer_id`, `ticket_id` |
| **Routes (URL)** | kebab-case | `/service-types`, `/budgets/{budget}/payments` |
| **Route names** | dot.case, kebab | `budgets.payments.store`, `tickets.update-status` |
| **Vue pages** | PascalCase folders/files | `Pages/Budgets/Index.vue` |
| **Vue partials** | `Partials/` subfolder, PascalCase | `Partials/BudgetForm.vue` |
| **Permissions** | kebab-case | `create service-orders`, `tickets.index-all` |
| **Language** | All code in English; user-facing text in sentence case | ✅ `"Save changes"` |

### 11.2 Validation

- Form Requests exist as a pattern but are **underutilized** (only `UploadInvoiceRequest` exists).
- Most validation is inline `$request->validate()`.
- Custom messages are rarely defined.

### 11.3 API Response Format

- `ServiceTypeController` and `TaskTemplateController` return JSON directly (not Inertia).
- `CalendarController::overview()` returns JSON for the top-bar badge.
- `NotificationController::fetch()` returns JSON for the bell dropdown.
- No API Resources (Laravel API Resource classes) are used.
- The dedicated `routes/api.php` is minimal (just `/user`).

### 11.4 Existing Modules That "Depósitos" Could Mirror

The project has several financial/operational modules with similar patterns:

1. **Budgets module** — The most structurally complete: models, controllers, services, versioned catalogs, payments. Has a layered architecture with Services and Actions.
2. **Invoices module** — Clean architecture with `InvoiceService` + `UploadInvoiceAction` + `UploadInvoiceRequest` Form Request. Good model for how new modules should be built.
3. **Costs module** — Uses `CostService` + `CostController` + `DispatchNotificationAction`. Shows how a service layer handles complex queries.
4. **Notifications module** — Shows the pattern for configuration-type modules with `NotificationService` + `NotificationController`.

---

## 12. Notes for the Upcoming "Depósitos" (Deposits) Module

### 12.1 Existing Models/Tables That May Relate

| Existing Entity | Potential Relationship |
|----------------|----------------------|
| **User** | Deposit creator/responsible; deposits may be assigned to users |
| **Customer** | Deposits may be linked to customer accounts (prepayments, advances) |
| **Budget** | Deposits could be prepayments applied against specific budgets |
| **BudgetPayment** | Existing payment tracking; deposits might be a different payment category |
| **TechnicianPayment** | Payments to technicians; deposits might fund technician pools |
| **Ticket** | Deposits may be associated with service tickets |
| **Employee** | Internal deposit management |

### 12.2 Architectural Decisions to Respect

1. **Follow the layered architecture**: Controller → Action → Service → Model. For the Deposits module, create:
   - `app/Models/Deposit.php`
   - `app/Http/Controllers/DepositController.php`
   - `app/Http/Requests/Deposits/` (StoreDepositRequest, UpdateDepositRequest)
   - `app/Services/Deposits/DepositService.php`
   - `app/Actions/Deposits/` (as needed)
   - `routes/web/deposits.php`

2. **Naming**: Use kebab-case routes (`/deposits`), dot-notation names (`deposits.index`), PascalCase Vue pages (`Pages/Deposits/Index.vue`).

3. **Validation**: Use Form Requests (not inline validation) — this is the project standard per the copilot instructions.

4. **Permissions**: Define new permissions under Spatie, use kebab-case (e.g., `deposits.index`, `deposits.create`, `deposits.edit`, `deposits.delete`). Check via `$request->user()->can()`.

5. **Frontend**: Use Element Plus components exclusively for UI. Use Inertia.js `useForm()` for forms. Use `ElTable` for data tables, `ElDialog` for modals.

6. **Media**: If deposits need file uploads (receipts, proofs), use Spatie MediaLibrary with `InteractsWithMedia` trait.

7. **Currency**: The system already supports MXN/USD with exchange rates. If deposits track currency, reuse the existing pattern from Budgets.

8. **Status workflow**: Follow the ticket/budget pattern of having a `status` field with clear states.

9. **Database**: Use snake_case, migrations in `database/migrations/`, foreign keys with cascade delete where appropriate.

10. **User-facing text**: All labels, messages, and UI text must use **sentence case** (not title case).

---

*End of document. This file was auto-generated by inspecting the actual codebase at `c:\Users\Miguel\Desktop\Sitios web\Construmax2` on 2026-07-14.*
