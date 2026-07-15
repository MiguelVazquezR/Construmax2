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
| Action | `CompleteDepositAction.php` | Creates TechnicianPayment + marks completed |
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
2. **Approve:** `ApproveDepositAction` sets `status=approved`, records `approved_by` and `approved_at`
3. **Complete:** `CompleteDepositAction` creates a `TechnicianPayment` record linking the deposit amount to the budget, sets `status=completed`, stamps `completed_at`, records `commission_amount`
4. **Notifications:** On creation → `deposit.pending-approval` notification sent to subscribers

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

### Public views
- Accessible via permanent signed URLs (Laravel `URL::signedRoute` with `absolute=true`)
- `Show`: Shows deposit details, bank account info (if approved/completed), completion button (if approved)
- `Day`: Shows all deposits for a date, grouped by shift, with bank info per deposit

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
- Click date to create, click event to edit
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
- **Tickets** (`06`): Deposits can be associated with tickets
- **Budgets** (`07`): `budget_id` is auto-derived; `CompleteDepositAction` creates a `TechnicianPayment` linked to the budget
- **Notifications** (`13`): `deposit.pending-approval` notification
- **Users** (`03`): `created_by`, `approved_by`

---

## Known limitations / cautions

- **Signed URLs are permanent:** Using `absolute=true` with no expiration — links never expire. This is intentional for long-lived technician access but means anyone with the link can view deposit details.
- **Completion from public page:** Technicians can mark deposits as completed via the public link — ensure authorization is appropriate for the business workflow.
- **Budget auto-derivation:** `budget_id` is set to `$ticket->budget->id` if the ticket has a budget — if no budget exists, it stays null. This silent fallback may cause issues if budget is created later.
- **Shift auto-detection:** Default shift is based on server time when the form loads — users can override it. This works for Mexico timezones but may need adjustment for other regions.
- **No bulk operations:** Each deposit must be created individually — no batch deposit creation for multiple technicians on the same day.
