# Construmax2 ERP — 00: Project Overview

> Context document for AI assistants that do not have direct access to the codebase.  
> Keep this under 300 lines — it's meant to be a "map" to the rest of the docs.

---

## Tech stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend framework | Laravel | 12.x |
| PHP | — | ^8.2 (target 8.3+) |
| Frontend framework | Vue 3 (Composition API, `<script setup>`) | ^3.3 |
| SPA bridge | Inertia.js | ^2.0 |
| Build tool | Vite | ^7.0 |
| UI library | Element Plus | ^2.13 |
| CSS utility | Tailwind CSS | ^3.4 |
| Auth (web) | Laravel Sanctum (SPA mode) | ^4.0 |
| Auth scaffolding | Laravel Jetstream (Inertia stack) | ^5.4 |
| RBAC | Spatie Laravel Permission | ^6.24 |
| File/media | Spatie Laravel Media Library | ^11.17 |
| Routing (JS) | Ziggy (tightenco/ziggy) | ^2.0 |
| Charts | ApexCharts (vue3-apexcharts) + ECharts (vue-echarts) | latest |

---

## Project structure

```
Construmax2/
├── app/
│   ├── Actions/          # Single-use-case orchestrators (per module)
│   │   ├── Deposits/     # ApproveDepositAction, CompleteDepositAction
│   │   ├── FieldWork/    # Create|Update|DeleteFieldWorkScheduleAction
│   │   ├── Fortify/      # CreateNewUser, ResetUserPassword, etc.
│   │   ├── Invoices/     # UploadInvoiceAction
│   │   ├── Jetstream/    # DeleteUser
│   │   └── Notifications/# DispatchNotificationAction
│   ├── Console/Commands/ # CheckOverdueInvoices, MigrateProductionData
│   ├── Http/
│   │   ├── Controllers/  # Thin controllers — delegate to Actions/Services
│   │   ├── Middleware/   # Standard Laravel + Jetstream middleware
│   │   └── Requests/     # Form Requests (all validation lives here)
│   │       ├── FieldWork/ # Store|UpdateFieldWorkScheduleRequest
│   ├── Models/           # 23 Eloquent models with rich relationships/scopes
│   ├── Notifications/    # 5 notification classes (mail + database)
│   ├── Providers/        # AppServiceProvider, Fortify, Jetstream
│   └── Services/         # Reusable business logic (per module)
│       ├── Costs/        # CostService
│       ├── Deposits/     # DepositService
│       ├── FieldWork/    # FieldWorkScheduleService
│       ├── Invoices/     # InvoiceService
│       ├── Media/        # ImageOptimizerService
│       └── Notifications/# NotificationService
├── config/               # Standard Laravel + Spatie + Jetstream + Sanctum
├── database/migrations/  # 34 migration files
├── resources/js/
│   ├── Pages/            # Inertia page components (one folder per module)
│   ├── Components/       # Reusable UI (shared + per-module subfolders)
│   ├── Composables/      # usePermissions.js, useCostsHelpers.js
│   └── Layouts/          # AppLayout.vue, AppSidebar.vue, AppTopbar.vue
├── routes/
│   ├── web.php           # Top-level routes + requires all web/*.php files
│   ├── web/              # 15 modular route files (one per module)
│   ├── api.php           # Sanctum-protected /api/user
│   └── console.php       # Scheduled command: check-overdue-invoices (daily 07:00)
└── docs/context/         # THIS DOCUMENTATION SET
```

---

## Architectural decisions

| Decision | Implementation |
|----------|---------------|
| **Auth strategy** | Session-based (Sanctum SPA mode) + Jetstream Inertia stack. No API tokens in use yet. 2FA available per-user. |
| **Authorization** | Spatie Laravel Permission — permissions are kebab-case (e.g., `create service-orders`). Roles are checked via permissions, never hardcoded. Teams feature disabled. |
| **State management** | Inertia.js `useForm()` and `usePage()` — no Vuex/Pinia. Server owns all state; the client is a thin render layer. |
| **API style** | Inertia responses (JSON props → Vue) for all page loads. `axios` calls for quick-create modals, notification polling. Public routes use Laravel signed URLs. |
| **ORM** | Eloquent with explicit `$fillable`, query scopes, accessors, and castings. No raw SQL in controllers. |
| **Validation** | 100% in Form Requests (`app/Http/Requests/`). Controllers never call `$request->validate()`. |
| **Architecture pattern** | Controller → Action → Service → Model. Controllers are thin (delegate immediately). Actions handle one use case. Services contain reusable logic. |
| **File uploads** | Spatie Media Library. Images auto-optimized via GD (1920px max, 75% quality). |
| **Notifications** | Laravel Notifications (mail + database). Dispatch is centralized in `DispatchNotificationAction`. Subscribers managed per notification type via `NotificationSetting`. |
| **Scheduling** | One cron: `notifications:check-overdue-invoices` daily at 07:00. |
| **Multi-currency** | MXN (default) + USD. Exchange rate fetched client-side from a proxy endpoint. Budget totals stored in original currency with an exchange rate field. |
| **Naming conventions** | Routes: kebab-case URLs. Permissions: kebab-case. User-facing text: sentence case. All code in English. |

---

## Module index

| # | Module | Context file | Description |
|---|--------|-------------|-------------|
| 1 | Auth | `02-module-auth.md` | Login, registration, 2FA, password reset, email verification (Fortify + Jetstream + Sanctum) |
| 2 | Users | `03-module-users-roles.md` | User CRUD, employee profiles, status toggle |
| 3 | Roles & Permissions | `03-module-users-roles.md` | Spatie RBAC: role CRUD, permission CRUD (developer-gated), assignment |
| 4 | Dashboard | `04-module-dashboard.md` | Main dashboard with my-day tasks, KPIs, analytics dashboard with charts |
| 5 | Customers | `05-module-customers.md` | Customer/prospect CRUD, branches, contacts, file uploads, logo management |
| 6 | Tickets | `06-module-tickets.md` | Service tickets (work orders): lifecycle, task management, task templates, evidence, Kanban |
| 7 | Budgets | `07-module-budgets.md` | Budgets linked to tickets: concepts, payments, technician payments, multi-currency, file uploads |
| 8 | Costs | `08-module-costs.md` | Cost catalogs (materials + labor), versioning, IVA, Empeño Fácil special calculations, print views |
| 9 | Technicians | `09-module-technicians.md` | Technician profiles, specialties, bank accounts, ratings, status management |
| 10 | Calendar | `10-module-calendar.md` | Dual-mode calendar: personal events with participant invitations + field work scheduling with task timestamp automation, Day/Week/Month views |
| 11 | Deposits | `11-module-deposits.md` | Deposit tracking: creation, approval workflow, bank accounts, public signed-URL views, shift management |
| 12 | Invoices | `12-module-invoices.md` | Invoice upload, tracking overdue invoices, status syncing with tickets |
| 13 | Notifications | `13-module-notifications.md` | 5 event types, subscriber management, notification bell with polling, cron-triggered overdue checks |
| 14 | Service Types | `06-module-tickets.md` | Simple CRUD for service type taxonomy used by tickets |
| 15 | Tutorials | `04-module-dashboard.md` | Hardcoded video tutorial gallery page |
| 16 | Work Acceptance Reports | `14-module-work-acceptance-reports.md` | Digital "Acta de recepción": technician data entry, electronic signature, PDF export, locking mechanism |

---

## Quick relationship map

```
User ──hasOne──▶ Employee
User ──hasOne──▶ Technician ──hasMany──▶ TechnicianBankAccount
User ──hasMany──▶ Ticket (as seller)
User ──belongsToMany──▶ Calendar (participants)

Customer ──hasMany──▶ CustomerBranch
Customer ──hasMany──▶ CustomerContact
CustomerBranch ◀──belongsToMany──▶ CustomerContact (pivot: customer_branch_contact)
Customer ──hasMany──▶ Ticket

Ticket ──hasOne──▶ Budget
Ticket ──hasMany──▶ TicketTask ──hasMany──▶ Media (task evidence)
Ticket ──hasMany──▶ Media (ticket evidence)
Ticket ──belongsTo──▶ Customer, CustomerBranch, CustomerContact, User (seller)

Budget ──hasMany──▶ BudgetConcept, BudgetPayment, BudgetCatalog, TechnicianPayment
BudgetCatalog ──hasMany──▶ BudgetCatalogItem
Budget ──hasOne──▶ BudgetCatalog (latest, via latestOfMany)
Budget ──hasOneThrough──▶ Customer (via Ticket)

Deposit ──belongsTo──▶ Technician, TechnicianBankAccount, Ticket, Budget, DepositType
TaskTemplate ──hasMany──▶ TaskTemplateItem
NotificationSetting ──belongsTo──▶ User
Ticket ──hasOne──▶ WorkAcceptanceReport
Ticket ──hasOne──▶ FieldWorkSchedule
FieldWorkSchedule ──belongsTo──▶ User (creator)
WorkAcceptanceReport ──belongsTo──▶ User (created_by)
```

---

## Files to read next

For detailed understanding of any module, read its dedicated file in this folder.  
For the complete database schema, start with `01-database-schema.md`.
