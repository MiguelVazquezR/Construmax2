# Construmax2 ERP — 13: Notifications Module

> **Purpose:** In-app and email notifications for key business events — subscriber management, notification bell with polling, automated cron-triggered checks.  
> **Context file covers:** 5 notification types, dispatch orchestration, subscriber settings, notification bell.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Action | `DispatchNotificationAction.php` | Centralized dispatch for all 5 event types |
| Service | `NotificationService.php` | Notify subscribers for a given notification type |
| Model | `NotificationSetting.php` | Per-user toggles for each notification type |
| Notification | `CatalogCreated.php` | Mail + database |
| Notification | `DepositPendingApproval.php` | Database only |
| Notification | `InvoiceOverdue.php` | Mail + database |
| Notification | `TicketNeedsCatalog.php` | Mail + database |
| Notification | `TicketNeedsInvoice.php` | Mail + database |
| Command | `CheckOverdueInvoices.php` | Cron: daily overdue invoice check |
| Vue component | `Components/NotificationBell.vue` | Bell icon with dropdown, 30s polling |
| Vue page | `Config/Notifications/Index.vue` | Admin: toggle notification types per user |
| Routes | `routes/web/notifications.php` | 8 routes |

---

## Routes

### Notification API
```
GET    /notifications/fetch              notifications.fetch
POST   /notifications/{id}/mark-read     notifications.mark-read
POST   /notifications/mark-all-read      notifications.mark-all-read
DELETE /notifications/{id}               notifications.delete
DELETE /notifications                     notifications.delete-all
```

### Notification Settings
```
GET    /config/notifications                   config.notifications.index
POST   /config/notifications                   config.notifications.store
DELETE /config/notifications/{setting}         config.notifications.destroy
```

---

## Five notification types

| Type key | Trigger | Channels | Who receives it |
|----------|---------|----------|----------------|
| `ticket.needs-catalog` | Ticket status → `Catálogo` | mail + database | All subscribers |
| `catalog.created` | BudgetCatalog created | mail + database | **Only the ticket's seller**, if they have the setting active |
| `ticket.needs-invoice` | Ticket status → `Finalizado` | mail + database | All subscribers |
| `invoice.overdue` | Cron detects due date reached | mail + database | All subscribers |
| `deposit.pending-approval` | Deposit created | database only | All subscribers |

---

## Dispatch architecture

### `DispatchNotificationAction`
Centralized class injected into:
- `Ticket` model (booted::updated) — for `ticketNeedsCatalog` and `ticketNeedsInvoice`
- `CheckOverdueInvoices` command — for `invoiceOverdue`
- Controllers that create catalogs/deposits — for `catalogCreated` and `depositPendingApproval`

### Special case: `catalogCreated`
Unlike other notifications that go to all subscribers, catalog creation only notifies the **ticket's seller** — and only if they have the `catalog.created` notification setting active. This uses an explicit `NotificationSetting` check rather than the `notifySubscribers` helper.

### `NotificationService::notifySubscribers(string $type, Notification $notification)`
- Queries `NotificationSetting::subscribersFor($type)` — returns users with active setting and valid email
- Sends the notification to each subscriber via Laravel's `Notification::send()`

---

## Email format
All notification emails are written in **Spanish** (user-facing content) with English class/variable names (code). Each includes relevant links to the ticket/budget/deposit and formatted amounts.

---

## Notification bell (`NotificationBell.vue`)

- Renders in the top navbar
- Shows unread count badge
- Dropdown lists notifications with: type icon, message, timestamp, mark-read button, delete button
- "Mark all read" and "Delete all" actions
- **Polls every 30 seconds** via `axios` to `/notifications/fetch`
- Permission-gated: only renders if user has appropriate permissions

---

## Notification settings page (`Config/Notifications/Index.vue`)

- Grid layout: rows = users, columns = notification types
- Toggle switches to enable/disable each type per user
- Types displayed with Spanish labels

---

## Dependencies on other modules

- **Users** (`03`): `NotificationSetting` is per-user; subscribers filtered by valid email
- **Tickets** (`06`): `ticketNeedsCatalog`, `ticketNeedsInvoice` triggered by status changes
- **Budgets** (`07`): Catalog creation triggers `catalogCreated`
- **Deposits** (`11`): Deposit creation triggers `depositPendingApproval`
- **Invoices** (`12`): Cron command triggers `invoiceOverdue`

---

## Known limitations / cautions

- **`catalogCreated` is an exception:** Unlike the other 4 types, it doesn't use `notifySubscribers` — it manually checks the seller's setting. If you want to change who receives catalog notifications, edit `DispatchNotificationAction::catalogCreated`.
- **No notification for calendar events:** Participant invitations exist in the data model but aren't wired to notifications
- **30-second polling:** The bell polls every 30s — if many users are active, this generates constant requests. Consider WebSockets (Laravel Reverb/Echo) for scale.
- **`deposit.pending-approval` is database-only:** No email is sent for deposit approvals — admins must check the notification bell
- **Notification settings page is admin-only:** Regular users cannot configure their own notification preferences from the profile page
- **No push notifications:** Only mail + database channels — no mobile push
