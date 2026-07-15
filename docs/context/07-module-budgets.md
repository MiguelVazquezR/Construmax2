# Construmax2 ERP — 07: Budgets Module

> **Business purpose:** Financial proposals linked to tickets — budget concepts, client payments, technician payments, file attachments, multi-currency support, and kanban pipeline.  
> **Context file covers:** Budgets CRUD, concepts, payments (client + technician), file uploads, kanban.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `BudgetController.php` | Full CRUD + payments, files, technician payments |
| Model | `Budget.php` | HasMany concepts/payments/catalogs; computed totals |
| Model | `BudgetConcept.php` | Budget line items |
| Model | `BudgetPayment.php` | Client payment records |
| Model | `TechnicianPayment.php` | Technician payment records |
| Vue pages | `Budgets/` | Index (table + kanban), Create, Edit, Show |
| Vue partials | `Budgets/Partials/` | 11 sub-components |
| Routes | `routes/web/budgets.php` | 14 routes |

---

## Routes

```
GET    /budgets                                      budgets.index
GET    /budgets/create                               budgets.create
POST   /budgets                                      budgets.store
GET    /budgets/{budget}                             budgets.show
GET    /budgets/{budget}/edit                        budgets.edit
PUT    /budgets/{budget}                             budgets.update
DELETE /budgets/{budget}                             budgets.destroy
POST   /budgets/{budget}/payments                    budgets.payments.store
DELETE /budgets/payments/{payment}                   budgets.payments.destroy
POST   /budgets/{budget}/technician-payments         budgets.technician-payments.store
DELETE /technician-payments/{payment}                budgets.technician-payments.destroy
POST   /budgets/{budget}/files                       budgets.files.store
POST   /budgets/bulk-upload-files                    budgets.bulk-upload-files
DELETE /budgets/files/{media}                        budgets.files.destroy
```

---

## Budget data model

### Core
- `ticket_id`: 1:1 with tickets
- `status`: Borrador → Enviado al cliente → Aprobado → Rechazado → Facturado
- `currency`: MXN or USD
- `exchange_rate`: decimal(10,4), used for MXN-equivalent calculations
- `user_id`: Responsible seller

### Computed attributes
| Attribute | Formula |
|-----------|---------|
| `total_cost` | `latestCatalog.total` OR `sum(concepts.amount)` |
| `total_paid` | `sum(payments.amount)` |
| `balance_due` | `total_cost - total_paid` |
| `total_catalog_cost` | `latestCatalog.total` (versioned catalog total) |

---

## Controller behavior

| Method | What it does |
|--------|-------------|
| `index` | List with search, status, user/branch filters; default filtered to current user |
| `store` | Creates budget + concepts, handles survey images; supports `quick_create` for JSON response from ticket flow |
| `show` | Loads with all relations and media for detail tabs |
| `update` | Updates budget + concepts + images |
| `storePayment` | Records client payment with proof; auto-marks ticket as "Pagado" when fully paid |
| `destroyPayment` | Deletes payment, reverts ticket status if needed |
| `storeTechnicianPayment` | Records payment to technician (proof mandatory) |
| `destroyTechnicianPayment` | Deletes technician payment |
| `storeFile` / `destroyFile` | Additional document management |
| `bulkUploadFiles` | Upload files to multiple budgets at once |

---

## Budget → Ticket status sync

When a budget payment brings the balance to zero → ticket is auto-marked as "Pagado". When a payment is deleted and balance becomes positive again → ticket status reverts.

When an invoice is uploaded → budget status becomes "Facturado" and ticket status becomes "Facturado".

---

## Vue pages

### `Budgets/Index.vue`
- Toggle between `TableList.vue` and `Kanban.vue` views
- Filters: search, status, perPage, branch, user_id (multi-select for advisors)
- Default filter: current user only

### `BudgetForm.vue` (core, ~200 lines)
- 3-column layout: form fields + summary panel + actions
- Ticket selection → auto-fills currency and technicians
- Currency selector (MXN/USD) → auto-fetches exchange rate from API proxy
- Concepts grid: add/remove rows with amount
- Survey images upload (picture-card style)
- `forceFormData` when files are attached

### Kanban columns
Cotización → Presupuesto enviado → Trabajo en proceso → Facturación → Facturado → Pagado → Perdido

### Detail tabs (Show.vue)
1. **Scope & Costs:** Catalog status + concepts table + scope description
2. **Files:** Survey images, invoice docs, budget files (upload/preview/delete)
3. **Finance:** Payments received, balance due, progress bar, payment recording modal
4. **Client:** Company name, contact, branch
5. **Ticket:** Linked ticket summary card

### `BudgetTechniciansSection.vue`
- Shows external technicians only (skips internal)
- Per-technician card: avatar, tasks progress, total paid
- Payment recording modal (mandatory proof upload)

---

## Dependencies on other modules

- **Tickets** (`06`): 1:1 relationship; status changes sync bidirectionally
- **Costs** (`08`): `total_cost` falls back to catalog total
- **Technicians** (`09`): Technician payments reference user IDs from ticket assignments
- **Customers** (`05`): HasOneThrough via Ticket
- **Invoices** (`12`): Invoice upload triggers budget status change
- **Deposits** (`11`): Deposits reference budgets

---

## Known limitations / cautions

- **Multi-currency is display-only:** All amounts stored in original currency; MXN equivalent computed client-side or via accessor. No server-side currency conversion or rate history.
- **Budget-to-ticket is 1:1:** No support for multiple budget revisions per ticket. The "versioned catalogs" (`budget_catalogs`) handle cost revisions instead.
- **Technician payments via JSON:** Since technicians are JSON arrays on tickets (not FK relationships), the `BudgetTechniciansSection` must parse these arrays to find external technicians. If technician data structure changes, this breaks.
- **Payment proof is not always mandatory:** Client payments accept optional proof; technician payments require it.
- **`total_cost` fallback logic:** Uses catalog total if it exists, otherwise sum of concepts. If both exist and diverge, the catalog version takes precedence.
