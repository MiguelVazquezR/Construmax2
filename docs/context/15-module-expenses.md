# Construmax2 ERP — 15: Expenses Module (Control de gastos)

> **Business purpose:** Track the company's outgoing expenses — general expenses, payments made against a budget (breakdown concepts, extra expenses and commissions) and technician/external deposits — with payment follow-up. No approval workflow.
> **Context file covers:** Expense registration (general or budget-linked) with multiple receipt uploads, editing, deletion, quick "mark as paid", budget expense management (breakdown payments, bulk mark-as-paid, extras and commissions), deposit mirroring (Marcar realizado with voucher + commission), listing with filters and summary cards, configurable expense categories catalog, folio generation.
> **Current scope:** Registration, management, receipts, categories, the budget expenses panel and the deposits integration implemented.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `app/Http/Controllers/ExpenseController.php` | `index`, `store`, `update`, `destroy`, `markPaid`, `export` — thin, delegates to Actions/Services |
| Controller | `app/Http/Controllers/BudgetExpenseController.php` | Budget expenses: remote `search` of budgets, `show` panel page, `storeConceptPayment`, `markConceptsPaid` (bulk) |
| Controller | `app/Http/Controllers/ExpenseCategoryController.php` | Categories catalog JSON API (`index`, `store`, `update`, `destroy`) |
| Actions | `app/Actions/Expenses/` | `CreateExpenseAction`, `UpdateExpenseAction`, `DeleteExpenseAction`, `MarkExpensePaidAction`, `RegisterBudgetConceptPaymentAction`, `MarkBudgetConceptsPaidAction`, `SyncDepositExpenseAction` |
| FormRequests | `app/Http/Requests/Expenses/` | `StoreExpenseRequest`, `UpdateExpenseRequest` (extends Store), `RegisterBudgetConceptPaymentRequest`, `MarkBudgetConceptsPaidRequest`, `StoreExpenseCategoryRequest`, `UpdateExpenseCategoryRequest` |
| Service | `app/Services/Expenses/ExpenseService.php` | Filtered paginated listing, export rows, summary aggregates and the shared `mapExpense` serializer |
| Service | `app/Services/Expenses/BudgetExpenseService.php` | Budget search for the picker and the budget expenses panel payload (breakdown + extras + totals, commission-aware) |
| Service | `app/Services/Export/XlsxWriterService.php` | Minimal XLSX writer (PHP zip extension, no dependencies): bold header/footer, column widths, `#,##0.00` for float values |
| Service | `app/Services/Expenses/ExpenseReceiptService.php` | Attach one or many receipts and remove them by media id (images optimized via `ImageOptimizerService`) |
| Model | `app/Models/Expense.php` | Statuses, payment methods, folio generation (`GAS-####`), budget links, scopes (`ofType`, `forBudget`), `status_label` / `payment_method_label` / `receipt_url` / `receipt_name` / `receipts`, media collection `receipt` (multiple files) |
| Model | `app/Models/ExpenseCategory.php` | Expense category catalog (`scopeActive`) |
| Model | `app/Models/BudgetConcept.php` | Breakdown concept; `expense()` hasOne links it to its payment expense |
| Route | `routes/web/expenses.php` | Authenticated routes (registered in `routes/web.php`) |
| Vue page | `resources/js/Pages/Expenses/Index.vue` | Summary cards, filters (type + budget), listing table, actions dropdown, pagination, expense type picker flow |
| Vue page | `resources/js/Pages/Expenses/BudgetExpenses.vue` | Budget expenses panel: breakdown payments, bulk mark-as-paid, extras (commissions included) |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseSummaryCards.vue` | The four summary cards |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseTypeDialog.vue` | First step of "Registrar gasto": choose general expense or budget expense |
| Vue partial | `resources/js/Pages/Expenses/Partials/BudgetPickerDialog.vue` | Remote budget picker with ticket-status filter (shows folio, name, customer and status tag) |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseFormDialog.vue` | Register/edit dialog: general expenses and budget extras (commissions are extras titled "Comisión"), multiple receipts, default date = today, amount and commission with $ prefix |
| Vue partial | `resources/js/Pages/Expenses/Partials/BudgetConceptPaymentDialog.vue` | Register/edit the payment of one breakdown concept (status, date, method, commission, reference, notes, receipts) |
| Vue partial | `resources/js/Pages/Expenses/Partials/BulkMarkConceptsPaidDialog.vue` | Registers a common payment for several selected concepts (optional transaction commission) |
| Vue partial | `resources/js/Pages/Expenses/Partials/CompleteDepositDialog.vue` | "Marcar depósito como realizado": uploads the voucher + commission and completes the linked deposit |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseReceiptsField.vue` | Shared multi-receipt uploader (append files, remove existing ones) |
| Vue partial | `resources/js/Pages/Expenses/Partials/ExpenseCategoriesManager.vue` | Categories manager modal (add by name / rename / deactivate / delete) |
| Vue partial | `resources/js/Pages/Budgets/Partials/BudgetExpensesCard.vue` | Budget show section: linked expenses, paid/pending footer and "Gestionar gastos" button |
| Migration | `database/migrations/2026_09_16_000001_create_expense_categories_table.php` | Categories catalog |
| Migration | `database/migrations/2026_09_16_000002_create_expenses_table.php` | Expenses table (no approval columns) |
| Migration | `database/migrations/2026_09_16_000003_drop_description_from_expense_categories_table.php` | Categories only need a name |
| Migration | `database/migrations/2026_09_17_000001_add_budget_link_to_expenses_table.php` | Adds `budget_id`, `budget_concept_id` (unique) and `is_commission` to `expenses` |
| Migration | `database/migrations/2026_09_17_000002_add_commission_to_expenses_table.php` | Adds `commission_amount` (payment channel fee, e.g. OXXO) to `expenses` |
| Migration | `database/migrations/2026_09_17_000003_add_deposit_link_to_expenses_table.php` | Adds `deposit_id` (unique) to `expenses` — mirror of the deposits module |
| Seeder | `database/seeders/ExpenseCategorySeeder.php` | 10 default categories (called from `DatabaseSeeder`) |
| Factory | `database/factories/ExpenseFactory.php` | States: `pending()`, `paid()`, `cancelled()` |
| Factory | `database/factories/ExpenseCategoryFactory.php` | Realistic category names |
| Test | `tests/Feature/ExpenseControllerTest.php` | 58 tests: permission gates, listing, filters (type/budget included), summary (commission-aware), folio, store (general + budget + commission + receipts), update (append/remove receipts, commission), mark paid, destroy, budget search, budget panel, concept payments (single + bulk + transaction commission), export |
| Test | `tests/Feature/DepositExpenseIntegrationTest.php` | 13 tests: mirror only after approval, mirror update/delete, completion from both modules, voucher/commission sync, guards (mark-paid, delete, voucher required, permissions) |
| Menu | `resources/js/Layouts/AppSidebar.vue` | Sidebar entry "Control de gastos" (gated by `expenses.index`) |

---

## Routes

| Method | URI | Name | Notes |
|--------|-----|------|-------|
| GET | `/expenses` | `expenses.index` | Requires permission `expenses.index` |
| POST | `/expenses` | `expenses.store` | General expense (`expense_category_id` required) or budget extra/commission (`budget_id`, category optional) (`expenses.create`) |
| GET | `/expenses/export` | `expenses.export` | Excel (.xlsx) report honouring the filters applied on the index (requires `expenses.index`) |
| GET | `/expenses/budgets/search` | `expenses.budgets.search` | JSON remote search of budgets for the picker; accepts `q`, `status` (ticket status) and `limit` (requires `expenses.create` or `expenses.edit`) |
| GET | `/expenses/budgets/{budget}` | `expenses.budgets.show` | Budget expenses panel (`Expenses/BudgetExpenses` page) — requires `expenses.index` |
| POST | `/expenses/budgets/{budget}/concepts/{concept}/payment` | `expenses.budgets.concepts.payment` | Creates/updates the paid expense of a breakdown concept (receipts supported) |
| POST | `/expenses/budgets/{budget}/concepts/mark-paid` | `expenses.budgets.concepts.mark-paid` | Registers a common payment for several concepts at once (bulk; optional transaction commission recorded once as a general commission) |
| PUT | `/expenses/{expense}` | `expenses.update` | Requires `expenses.edit` |
| DELETE | `/expenses/{expense}` | `expenses.destroy` | Requires `expenses.delete` |
| POST | `/expenses/{expense}/mark-paid` | `expenses.mark-paid` | Quick action: status → `paid`, stamps `paid_at` (requires `expenses.edit`); blocked for deposit expenses |
| POST | `/expenses/{expense}/complete-deposit` | `expenses.complete-deposit` | Completes the linked deposit (voucher + commission) and syncs both modules — requires `expenses.edit` or `deposits.approve` |
| GET | `/expenses/categories` | `expenses.categories.index` | Categories catalog JSON (with `expenses_count`) — requires `expenses.categories.manage` |
| POST | `/expenses/categories` | `expenses.categories.store` | Create category |
| PUT | `/expenses/categories/{category}` | `expenses.categories.update` | Rename / toggle active |
| DELETE | `/expenses/categories/{category}` | `expenses.categories.destroy` | Delete category (expenses fall back to "Sin categoría" via `nullOnDelete`) |

All write actions redirect `back()` so the index (or the budget panel) filters are preserved after saving. Concept payment writes require `expenses.create` or `expenses.edit`.

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
| `expense_category_id` | FK → expense_categories.id, nullOnDelete | nullable — required for general expenses, optional for budget expenses |
| `ticket_id` | FK → tickets.id, nullOnDelete | nullable — legacy link, no longer set from the UI |
| `budget_id` | FK → budgets.id, nullOnDelete | nullable — set for budget expenses (extras and commissions; concept payments also set it) |
| `budget_concept_id` | FK → budget_concepts.id, nullOnDelete, unique | nullable — set when the expense pays a breakdown concept (one expense per concept) |
| `deposit_id` | FK → deposits.id, nullOnDelete, unique | nullable — expense mirroring a deposit (managed from the deposits module; see the deposits integration section) |
| `concept` | string | Short description of the expense (copied from the concept for concept payments) |
| `reference` | string | nullable — receipt / invoice number |
| `notes` | text | nullable |
| `amount` | decimal(12,2) | For concept payments it is always taken from the concept (client value ignored) |
| `commission_amount` | decimal(12,2) | nullable — fee charged by the payment channel (e.g. OXXO) on top of the amount; included in every total |
| `expense_date` | date | For concept payments it is the payment date |
| `payment_method` | string | nullable — `cash`, `transfer`, `card`, `check`, `other` |
| `status` | string | default `pending` — `pending`, `paid`, `cancelled` |
| `is_commission` | boolean | default `false` — true for commissions registered as budget extras |
| `created_by` | FK → users.id | User who registered the expense |
| `paid_at` | timestamp | nullable — stamped by the mark-paid action or by edit when status = `paid` |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `belongsTo(ExpenseCategory)`, `belongsTo(Ticket)` (legacy), `belongsTo(Budget)`, `belongsTo(BudgetConcept)`, `belongsTo(User, created_by)`

**Media:** receipts (comprobantes) are stored with Spatie Media Library in the `receipt` collection (multiple files per expense). The model appends `receipt_url` / `receipt_name` (first file, kept for backwards compatibility) and `receipts` (`[{ id, url, name }]`); images are optimized before storage, PDFs are stored as-is. Limits: JPG, PNG, WEBP or PDF, max 10 MB per file, up to 5 files per upload request. Files are appended on edit and removed by id (`remove_receipt_ids`).

**Statuses:** `pending` (Pendiente de pago) → `paid` (Pagado), plus `cancelled` (Cancelado). There is no approval step.

**Payment methods:** `cash` (Efectivo), `transfer` (Transferencia), `card` (Tarjeta), `check` (Cheque), `other` (Otro).

**Budget expense semantics:**

- **Concept payment:** one expense per `budget_concept` (enforced by a unique index). The action copies concept/amount/budget from the concept and keeps `budget_concepts.payment_date` in sync (set when paid, cleared otherwise). When an existing concept expense is edited from the generic dialog, concept and amount are forced back to the breakdown values.
- **Extra expense / commission:** expense with `budget_id` and no `budget_concept_id`; commissions also set `is_commission = true`. These never alter the budget breakdown.
- **Commission (`commission_amount`):** the fee charged by the payment channel (e.g. paying at OXXO). It is captured on each expense — general, budget extra or concept payment — and optionally once per bulk payment (the "transaction commission", stored as a general commission expense). Totals (index cards, export total and the budget panel) count `amount + commission_amount`. A general commission expense (`is_commission = true`) does not carry a commission of its own.
- **Deposit expense:** mirror of a deposit (`deposit_id`), created/refreshed by `SyncDepositExpenseAction`. Money fields, status and receipts belong to the deposits flow: pending ones read as "Pendiente de depósito" and are completed with the **Realizado** action (`expenses.complete-deposit`), which stores the voucher + commission and runs the standard deposit completion (technician payment included).
- This module tracks outgoing money; `budget_payments` (customer payments) remain untouched — they track incoming money.

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

- **Filters (server-side):** `search` (concept or reference), `folio`, `status`, `category_id`, `payment_method`, `type` (`general` / `budget` / `commission` / `deposit`), `budget_id`, `from` / `to` (date range). 15 rows per page.
- **Sorting:** the folio, date, budget and status columns sort server-side through `sort_by` + `sort_dir` (allowlist in `ExpenseService::SORTABLE_COLUMNS`), falling back to newest `expense_date` first. Statuses sort in workflow order (pending → paid → cancelled).
- **Budget filter options:** the controller passes a `budgets` prop with the budgets that already have expenses (folio, project name, customer).
- **Summary cards:** *total registered*, *pending payment*, *de presupuesto* (expenses linked to a budget — includes breakdown payments, extras and commissions — with their share of the total) and *paid*. Every amount counts `amount + commission_amount`; the `status` filter is intentionally excluded from the summary query so every card keeps its totals while browsing a specific status.
- **Columns:** folio (compact, sortable), date (sortable), concept (with reference and "Comisión" / "Depósito" tags), category (ellipsis + tooltip when long), budget (compact folio link to the budget panel, sortable), amount (with the commission shown under it), status (full label, sortable), registered by (ellipsis + tooltip) and actions.
- **Payment method:** shown as a colored icon with tooltip right next to the amount (cash, transfer, card, check, other) — no dedicated column.
- **Receipts:** when the expense has files, a document icon button opens the first one in a new tab (tooltip shows the file count when there are several). The icon slot is always reserved so the ⋯ menu stays aligned in every row.
- **Row actions:** a single "more" (⋯) dropdown holds *marcar como pagado* (only when pending), *editar* and *eliminar*, each item gated by its permission. Deposit expenses show a blue **Realizado** button instead (same style as the deposits module): it opens `CompleteDepositDialog` (voucher + commission) and both mark-as-paid and delete are blocked (UI + server-side).
- **Register expense flow:** the "Registrar gasto" button first opens a type picker (`ExpenseTypeDialog`):
  - *Gasto general* opens the register dialog directly (category required).
  - *Gasto de presupuesto* opens `BudgetPickerDialog` (remote search + ticket-status filter, each option shows folio, project, customer and a status tag), then navigates to the budget expenses panel.
- **Register/edit dialog:** concept, category (optional for budget extras), amount, commission (optional, "Comisión del canal de pago — p. ej. cobro en OXXO"), date (defaults to today on create), status, payment method, notes and multiple receipt uploads (`ExpenseReceiptsField`). Concept payments show concept/amount locked with a hint that they are managed from the budget breakdown.
- **Excel report:** the "Descargar reporte" button downloads an `.xlsx` file with every filtered row (no pagination): folio, date, concept, reference, category, budget (folio + project), commission, payment method, status, amount, receipt (Sí/No), registered by and notes, plus a bold total row (amount + commission). Generated with `XlsxWriterService` using the current filters (`getExportRows`).
- **Categories manager:** the gear icon next to the category label opens the manager modal (gated by `expenses.categories.manage`) where categories can be added, edited, deactivated or deleted. When the catalog changes, the index reloads only `categories`, `expenses` and `stats` props.
- **Budget column:** links to `expenses.budgets.show` (the user already has `expenses.index`, same permission the panel requires).
- The sidebar entry is only rendered when the user has `expenses.index`.

---

## Budget expenses panel (`Expenses/BudgetExpenses`)

Opened from the type picker (`Gasto de presupuesto`) or from the "Gestionar gastos" button in the budget show page (`Budgets/Partials/BudgetExpensesCard.vue`).

- **Header:** back to expenses, budget folio + project + customer, ticket status tag and a "Ver presupuesto" link (gated by `budgets.index`). When the ticket is *Cancelado*, a warning alert is shown and every write action is disabled (read-only).
- **Summary cards:** *Desglose* (sum of breakdown concepts), *Adicionales* (extras + commissions, with their channel fees), *Pagado* (linked expenses with status `paid`, including commissions) and *Por pagar* (unpaid concepts + pending extras, including commissions).
- **Breakdown table:** every `BudgetConcept` with concept, amount (commission shown under it), "pago a técnico" tag, derived state (*Pendiente* when no expense exists, otherwise the expense status), payment date, reference, receipts and the *Registrar pago* / *Editar pago* row action (`BudgetConceptPaymentDialog`, which includes the commission field).
- **Bulk payment:** row checkboxes + "Marcar pagados (N)" (`BulkMarkConceptsPaidDialog`) register a common date/method/reference/notes for the selected concepts; one paid expense is created per concept. The optional "Comisión de la transacción" is recorded once as a general commission expense of the budget (never split across concepts).
- **Extras table:** budget expenses without a concept, including general commissions. Only "Agregar gasto" is available — a general commission is registered as an extra expense titled "Comisión". Row actions: mark as paid (pending only), edit and delete (reusing `expenses.mark-paid`, `expenses.update` and `expenses.destroy`).
- **Amounts** are formatted with the budget currency (`budget.currency`); the expenses index keeps MXN formatting.
- **Deleting an expense** (from the panel or the index) makes its concept go back to *Pendiente*; the concept payment date is cleared by the next payment edit.

---

## Deposits integration

Deposits (module 11) are mirrored as expenses so all outgoing money lives here too:

- `deposits.expense()` / `expenses.deposit_id` (unique). `SyncDepositExpenseAction` creates and refreshes the mirror (amount, commission, budget, concept, date, status; reference and notes only on creation) — **only once the deposit is approved**; pending deposits are not shown here.
- Approved-but-uncompleted deposits read as **"Pendiente de depósito"**. When the deposit is completed (internal, public link or from expenses) the expense becomes paid, receives the commission and the voucher is copied into its receipts.
- From this module the **Realizado** button completes the deposit with voucher + commission through the standard `CompleteDepositAction` (technician payment included). Requires `expenses.edit` or `deposits.approve`.
- Deposit expenses are read-mostly here: no mark-as-paid, no delete; the edit dialog locks amount/date/status/payment method/receipts (reference, notes and category stay editable). Deleting the deposit deletes the mirror expense.
- External deposits (no ticket/budget) are mirrored as general expenses: `Depósito externo: {tipo} — {beneficiario}`.

---

## Pending work (next iterations)

- Concept payments marked as "pago a técnico" only register the expense; deposits are a separate flow that DOES create technician payments when completed (see the deposits integration section).
- Commissions are payment-channel fees (`commission_amount`) or general commission expenses (`is_commission = true`, used for bulk transaction fees); there is no payee or commission catalog. The standalone "Agregar comisión" button was removed — a general commission is just an extra expense titled as such.
- Legacy expenses linked to tickets (`ticket_id`) are kept as historical data and behave as general expenses in the UI.
