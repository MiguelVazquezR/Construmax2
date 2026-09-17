# Construmax2 ERP — 15: Expenses Module (Control de gastos)

> **Business purpose:** Track the company's outgoing expenses — general expenses and purchases linked to projects (tickets) — with payment follow-up. No approval workflow.
> **Context file covers:** Expense registration (general or ticket-linked) with receipt upload, editing, deletion, quick "mark as paid", listing with filters and summary cards, configurable expense categories catalog, folio generation.
> **Current scope:** Registration, management, receipts and categories management implemented.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `app/Http/Controllers/ExpenseController.php` | `index`, `store`, `update`, `destroy`, `markPaid`, `searchTickets` — thin, delegates to Actions |
| Controller | `app/Http/Controllers/ExpenseCategoryController.php` | Categories catalog JSON API (`index`, `store`, `update`, `destroy`) |
| Actions | `app/Actions/Expenses/` | `CreateExpenseAction`, `UpdateExpenseAction`, `DeleteExpenseAction`, `MarkExpensePaidAction` |
| FormRequests | `app/Http/Requests/Expenses/` | `StoreExpenseRequest`, `UpdateExpenseRequest` (extends Store), `StoreExpenseCategoryRequest`, `UpdateExpenseCategoryRequest` |
| Service | `app/Services/Expenses/ExpenseService.php` | Filtered paginated listing + summary aggregates |
| Service | `app/Services/Expenses/ExpenseReceiptService.php` | Attach/replace/remove the receipt (images optimized via `ImageOptimizerService`) |
| Model | `app/Models/Expense.php` | Statuses, payment methods, folio generation (`GAS-####`), scopes, `status_label` / `payment_method_label` / `receipt_url` / `receipt_name`, media collection `receipt` |
| Model | `app/Models/ExpenseCategory.php` | Expense category catalog (`scopeActive`) |
| Route | `routes/web/expenses.php` | Authenticated routes (registered in `routes/web.php`) |
| Vue page | `resources/js/Pages/Expenses/Index.vue` | Summary cards, filters, listing table (compact folio, truncated category with tooltip), payment/receipt icons next to the amount, actions dropdown, pagination |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseSummaryCards.vue` | The four summary cards |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseFormDialog.vue` | Register/edit dialog: remote ticket search, receipt upload, default date = today, amount with $ prefix |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseCategoriesManager.vue` | Categories manager modal (add by name / rename / deactivate / delete) |
| Migration | `database/migrations/2026_09_16_000001_create_expense_categories_table.php` | Categories catalog |
| Migration | `database/migrations/2026_09_16_000002_create_expenses_table.php` | Expenses table (no approval columns) |
| Migration | `database/migrations/2026_09_16_000003_drop_description_from_expense_categories_table.php` | Categories only need a name |
| Seeder | `database/seeders/ExpenseCategorySeeder.php` | 10 default categories (called from `DatabaseSeeder`) |
| Factory | `database/factories/ExpenseFactory.php` | States: `pending()`, `paid()`, `cancelled()` |
| Factory | `database/factories/ExpenseCategoryFactory.php` | Realistic category names |
| Test | `tests/Feature/ExpenseControllerTest.php` | 26 tests: permission gates, listing, filters, summary, folio, store (general + ticket + receipt), update (replace/remove receipt), mark paid, destroy, ticket search, categories CRUD |
| Menu | `resources/js/Layouts/AppSidebar.vue` | Sidebar entry "Control de gastos" (gated by `expenses.index`) |

---

## Routes

| Method | URI | Name | Notes |
|--------|-----|------|-------|
| GET | `/expenses` | `expenses.index` | Requires permission `expenses.index` |
| POST | `/expenses` | `expenses.store` | General or ticket-linked expense (`expenses.create`) |
| GET | `/expenses/tickets/search` | `expenses.tickets.search` | JSON remote search for the optional ticket link (requires `expenses.create` or `expenses.edit`) |
| PUT | `/expenses/{expense}` | `expenses.update` | Requires `expenses.edit` |
| DELETE | `/expenses/{expense}` | `expenses.destroy` | Requires `expenses.delete` |
| POST | `/expenses/{expense}/mark-paid` | `expenses.mark-paid` | Quick action: status → `paid`, stamps `paid_at` (requires `expenses.edit`) |
| GET | `/expenses/categories` | `expenses.categories.index` | Categories catalog JSON (with `expenses_count`) — requires `expenses.categories.manage` |
| POST | `/expenses/categories` | `expenses.categories.store` | Create category |
| PUT | `/expenses/categories/{category}` | `expenses.categories.update` | Rename / change description / toggle active |
| DELETE | `/expenses/categories/{category}` | `expenses.categories.destroy` | Delete category (expenses fall back to "Sin categoría" via `nullOnDelete`) |

All write actions redirect `back()` so the index filters are preserved after saving.

---

## Data model

### `expense_categories`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string unique | Sentence case, Spanish |
| `is_active` | boolean | default `true` |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `hasMany(Expense)`

### `expenses`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `folio` | string unique | Auto-generated `GAS-0001`, `GAS-0002`, … |
| `expense_category_id` | FK → expense_categories.id, nullOnDelete | nullable |
| `ticket_id` | FK → tickets.id, nullOnDelete | nullable — optional project link |
| `concept` | string | Short description of the expense |
| `reference` | string | nullable — receipt / invoice number |
| `notes` | text | nullable |
| `amount` | decimal(12,2) | |
| `expense_date` | date | |
| `payment_method` | string | nullable — `cash`, `transfer`, `card`, `check`, `other` |
| `status` | string | default `pending` — `pending`, `paid`, `cancelled` |
| `created_by` | FK → users.id | User who registered the expense |
| `paid_at` | timestamp | nullable — stamped by the mark-paid action or by edit when status = `paid` |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `belongsTo(ExpenseCategory)`, `belongsTo(Ticket)`, `belongsTo(User, created_by)`

**Media:** the receipt (comprobante) is stored with Spatie Media Library in the `receipt` collection (single file). The model appends `receipt_url` and `receipt_name`; images are optimized before storage, PDFs are stored as-is. Limits: JPG, PNG, WEBP or PDF, max 10 MB.

**Statuses:** `pending` (Pendiente de pago) → `paid` (Pagado), plus `cancelled` (Cancelado). There is no approval step.

**Payment methods:** `cash` (Efectivo), `transfer` (Transferencia), `card` (Tarjeta), `check` (Cheque), `other` (Otro).

---

## Permissions

Defined in `database/seeders/PermissionSeeder.php` under the `Control de gastos` category:

| Permission | Purpose |
|------------|---------|
| `expenses.index` | See the listing, summary cards and filters |
| `expenses.create` | Register new expenses |
| `expenses.edit` | Edit expenses and mark them as paid |
| `expenses.delete` | Delete expenses |
| `expenses.categories.manage` | Manage the expense categories catalog |

---

## Index behavior

- **Filters (server-side):** `search` (matches folio, concept or reference), `status`, `category_id`, `from` / `to` (date range). 15 rows per page, latest `expense_date` first.
- **Summary cards:** *total registered*, *pending payment*, *in projects* (expenses linked to a ticket, with their share of the total) and *paid*. The `status` filter is intentionally excluded from the summary query so every card keeps its totals while browsing a specific status.
- **Columns:** folio (compact), date, concept (with reference), category (ellipsis + tooltip when long), ticket, amount, status, registered by and actions.
- **Payment method:** shown as a colored icon with tooltip right next to the amount (cash, transfer, card, check, other) — no dedicated column.
- **Receipt:** when the expense has one, an icon button next to the amount opens it in a new tab — no dedicated column.
- **Row actions:** a single "more" (⋯) dropdown holds *marcar como pagado* (only when pending), *editar* and *eliminar*, each item gated by its permission.
- **Register/edit dialog:** concept, category, amount, date (defaults to today on create), status, payment method, optional ticket (remote search by folio, project or customer name), notes and receipt upload.
- **Categories manager:** the gear icon next to the category label opens the manager modal (gated by `expenses.categories.manage`) where categories can be added, edited, deactivated or deleted. When the catalog changes, the index reloads only `categories`, `expenses` and `stats` props.
- **Ticket column:** links to the ticket only when the user has `tickets.index`.
- The sidebar entry is only rendered when the user has `expenses.index`.

---

## Pending work (next iterations)

- None defined for the current scope.
