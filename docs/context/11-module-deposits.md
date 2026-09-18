# Construmax2 ERP — 11: Deposits Module

> **Purpose:** Cash deposit tracking for technician payments — creation, approval workflow, bank account selection, public signed-URL views, shift management.  
> **Context file covers:** Deposits CRUD, approval, completion, bank accounts, public views, deposit types.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `DepositController.php` | CRUD + approve, links, bank accounts, pending tickets |
| Controller | `PublicDepositController.php` | Public signed-URL views + complete action |
| Controller | `DepositTypeController.php` | Deposit types CRUD (JSON API) |
| Action | `ApproveDepositAction.php` | Marks deposit as approved |
| Action | `CompleteDepositAction.php` | Creates TechnicianPayment + marks completed + syncs the mirror expense |
| Action | `App\Actions\Expenses\SyncDepositExpenseAction.php` | Mirrors the deposit in the expenses module (create/refresh expense + voucher copy) |
| Service | `DepositService.php` | Pending amounts, pending tickets, default shift |
| Model | `Deposit.php` | Implements HasMedia; status scopes |
| Model | `DepositType.php` | Simple taxonomy |
| Vue pages | `Deposits/Index.vue` | Hub with list/calendar toggle |
| Vue pages | `Public/Deposits/Show.vue` | Public single deposit view |
| Vue pages | `Public/Deposits/Day.vue` | Public daily summary view |
| Vue partials | `Deposits/Partials/` | 5 sub-components |
| Routes | `routes/web/deposits.php` | 16 routes (3 public + 13 auth) |

---

## Routes

### Authenticated
```
GET    /deposits                                           deposits.index
POST   /deposits                                           deposits.store
PUT    /deposits/{deposit}                                 deposits.update
DELETE /deposits/{deposit}                                 deposits.destroy
POST   /deposits/{deposit}/approve                         deposits.approve
GET    /deposits/{deposit}/public-link                     deposits.public-link
GET    /deposits/public-link/day/{date}                    deposits.day-link
GET    /deposits/technicians/{technician}/bank-accounts    deposits.technician-bank-accounts
GET    /deposits/technicians/{technician}/pending-tickets  deposits.technician-pending-tickets
GET    /deposits/types                                     deposits.types.index
POST   /deposits/types                                     deposits.types.store
PUT    /deposits/types/{depositType}                       deposits.types.update
DELETE /deposits/types/{depositType}                       deposits.types.destroy
```

### Public (signed URLs, no auth)
```
GET    /d/{deposit}                    public.deposits.show
GET    /d/day/{date}                   public.deposits.day
POST   /d/{deposit}/complete           public.deposits.complete
```

---

## Deposit lifecycle

```
pending ──▶ approved ──▶ completed
```

### Flow
1. **Create:** Admin/manager creates a deposit — selects technician, bank account, ticket, deposit type, amount, shift, scheduled date
2. **Approve:** `ApproveDepositAction` sets `status=approved`, records `approved_by` and `approved_at`, and mirrors the deposit as a pending expense in the expenses module
3. **Complete:** `CompleteDepositAction` creates a `TechnicianPayment` record linking the deposit amount to the budget, sets `status=completed`, stamps `completed_at`, records `commission_amount`. Once completed, cannot be re-completed (guarded server-side). The mirror expense (see "Expenses integration") is updated as paid, with the commission and the voucher copied as a receipt.
4. **Notifications:** On creation → `deposit.pending-approval` notification sent to subscribers

---

## Expenses integration

Approved deposits are mirrored in the **expenses module** (`expenses.deposit_id`, unique) so all outgoing money is tracked in one place (module 15):

- **Approve** (`ApproveDepositAction`) → `SyncDepositExpenseAction` creates the pending mirror expense: amount, budget (ticket deposits), concept (`Depósito: {tipo} — {técnico}` / `Depósito externo: {tipo} — {beneficiario}`), reference `Depósito #{id}` and the deposit notes. **Pending deposits are not mirrored** — the expense appears once the deposit is approved — and it reads as **"Pendiente de depósito"**.
- **Complete** (internal, public link or from expenses) → the mirror expense becomes **paid**, gets the commission and the voucher is copied into its receipts.
- **Update** → amount, type, date and concept are refreshed on the mirror; **Delete** → the mirror expense is removed too.
- **From expenses:** the **Realizado** action (`POST /expenses/{expense}/complete-deposit`) uploads the voucher + commission and runs this same `CompleteDepositAction`, so the technician payment and status stay official. Requires `expenses.edit` or `deposits.approve`.
- Deposit expenses cannot be mark-paid or deleted from the expenses module (guarded server-side), and their amount/date/status/receipts are locked in the expense edit dialog.

---

## Data model

### Deposit
- `technician_id`: FK to technicians
- `technician_bank_account_id`: FK to bank account (determines where money is deposited)
- `ticket_id`: FK to tickets (nullable)
- `budget_id`: FK to budgets (nullable, auto-derived from ticket when possible)
- `deposit_type_id`: FK to deposit_types
- `amount`: decimal(10,2)
- `shift`: `matutino` or `vespertino` (auto-detected: vespertino if hour ≥ 15)
- `scheduled_date`: date
- `status`: pending → approved → completed
- `created_by` / `approved_by`: FK to users
- `commission_amount`: set on completion
- `technician_payment_id`: FK to technician_payments, linked on completion
- Has media: `voucher` (single file)

---

## Key behaviors

### DepositService
- `pendingAmountForTechnician(Technician, Budget)`: Calculates unpaid amount for a technician on a budget
- `pendingTicketsForTechnician(Technician)`: Finds tickets where the technician has pending payments
- `defaultShift()`: Returns `vespertino` if current hour ≥ 15, else `matutino`

### List view filters
- **Technician:** Select dropdown that loads all external technicians on mount (single request), with local filtering by name — avoids request-per-keystroke lag.
- **Status:** Defaults to `pending`. Options: Pendiente, Aprobado, Completado, "Todos los estados" (empty value = no filter).
- **Shift:** Defaults to "Ambos turnos" (empty value = no filter). Options: Matutino, Vespertino.
- **Filter params:** Always sent to the server. Empty values mean "show all". Status defaults to `pending` only when the param is completely absent from the URL.
- ⚠️ **PHP quirk:** Query string `?shift=` is parsed as `null` by PHP, not `""`. Backend checks for both `''` and `null` when determining "show all".

### List view — ticket column
- If user has `tickets.index` permission: the ticket folio is an Inertia `<Link>` to `tickets.show`.
- Otherwise: plain text.

### Calendar view
- Element Plus Calendar with color-coded events (blue=matutino, orange=vespertino).
- **"+" button:** Circular `Plus` icon button in each day cell header. Visible on hover for desktop (`opacity` transition), always visible on touch devices via `@media (hover: none)`.
- Clicking an event opens the edit modal (non-completed only).
- Date formatting: `15 jul, 2026` format (`toLocaleDateString('es-MX')`).
- Amount formatting: comma thousand separators (`$1,234.56`).

### Public views
- Accessible via permanent signed URLs (Laravel `URL::signedRoute`, no expiration).
- **Show:** Shows deposit details and bank account info only if `approved` or `completed`. For `pending`, only shows "Pendiente de aprobación" notice (no technician, amount, or banking data).
- **Day:** Shows deposits grouped by shift. Pending deposits show only technician name + notice. Approved/completed deposits have expandable banking details via inline toggle (no page navigation needed, avoiding 403 on unsigned routes).
- **QR image:** Bank account QR (`bank_qr` media collection) displayed below banking details in both Show and Day views.
- **Complete action:** Uses a signed URL (passed from backend via `completeUrl` prop) to avoid 403. Guarded server-side against re-completion. Confirmation modal is mobile-responsive (90% width, max 480px).
- **Status labels:** Displayed in Spanish: "Pendiente", "Aprobado", "Realizado".
- **Amounts:** Comma-separated thousands, 2 decimal places.
- **Dates:** `15 jul, 2026` format.

### Bank account selection
- When creating a deposit, selecting a technician loads their bank accounts
- The favorite account is pre-selected

---

## Vue pages

### `Deposits/Index.vue`
- Toggle between list view (`DepositListView`) and calendar view (`DepositCalendarView`)
- Deposit creation/editing via `DepositForm` modal
- Deposit types management via `DepositTypesManager` modal
- Share link generation via `ShareLinkDialog`
- Approval workflow buttons

### `DepositCalendarView.vue`
- Element Plus Calendar with color-coded events (blue=matutino, orange=vespertino)
- "+" button per day cell for creating deposits (hover on desktop, always visible on touch)
- Click event to edit
- Status-based opacity (pending=full, approved=medium, completed=light)

### `DepositForm.vue`
- Technician search (loads all, filters locally)
- Bank account dropdown (loaded on technician change)
- Ticket association with pending payment calculation
- Deposit type selector (with inline manager)
- Shift and scheduled date

### `ShareLinkDialog.vue`
- Generates permanent signed URLs
- Single deposit link + day link options
- Copy-to-clipboard

---

## Dependencies on other modules

- **Technicians** (`09`): Deposits reference technicians and their bank accounts
- **Tickets** (`06`): Deposits can be associated with tickets; ticket folio links to ticket details
- **Budgets** (`07`): `budget_id` is auto-derived; `CompleteDepositAction` creates a `TechnicianPayment` linked to the budget
- **Expenses** (`15`): deposits are mirrored as expenses (`SyncDepositExpenseAction`); completion syncs status/commission/voucher both ways, and the voucher can be uploaded from the expenses **Realizado** flow
- **Notifications** (`13`): `deposit.pending-approval` notification
- **Users** (`03`): `created_by`, `approved_by`
- **Dashboard** (`04`): Dashboard KPI card shows pending deposit count and today's scheduled deposits

---

## Known limitations / cautions

- **Signed URLs are permanent:** No expiration — links never expire. This is intentional for long-lived technician access but means anyone with the link can view deposit details.
- **Completion from public page:** Technicians can mark deposits as completed via the public link. The complete endpoint uses a signed URL (passed from backend, not generated client-side) to prevent 403 errors.
- **Budget auto-derivation:** `budget_id` is set to `$ticket->budget->id` if the ticket has a budget — if no budget exists, it stays null. This silent fallback may cause issues if budget is created later.
- **Shift auto-detection:** Default shift is based on server time when the form loads — users can override it. This works for Mexico timezones but may need adjustment for other regions.
- **No bulk operations:** Each deposit must be created individually — no batch deposit creation for multiple technicians on the same day.
- **Completion from expenses skips approval by design:** the expenses "Realizado" flow completes deposits in `pending` or `approved` status (voucher required unless one already exists), same as creating a deposit with a voucher.
- **PHP null coercion:** Empty query string values (`?shift=`) are parsed as `null` by PHP. Filter logic must explicitly check for both `''` and `null` when determining "show all".
