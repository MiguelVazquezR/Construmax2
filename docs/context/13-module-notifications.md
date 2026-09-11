# Construmax2 ERP — 13: Notifications Module

> **Purpose:** In-app and email notifications for key business events — subscriber management, notification bell with polling, automated cron-triggered checks.  
> **Context file covers:** 6 notification types, dispatch orchestration, subscriber settings, notification bell.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Action | `DispatchNotificationAction.php` | Centralized dispatch for all 6 event types |
| Service | `NotificationService.php` | Notify subscribers for a given notification type |
| Model | `NotificationSetting.php` | Per-user toggles for each notification type |
| Notification | `CatalogApproved.php` | Mail + database |
| Notification | `CatalogNeedsUpdate.php` | Mail + database |
| Notification | `DepositPendingApproval.php` | Database only |
| Notification | `InvoiceOverdue.php` | Mail + database |
| Notification | `TicketNeedsCatalog.php` | Mail + database |
| Notification | `TicketNeedsInvoice.php` | Mail + database |
| Command | `CheckOverdueInvoices.php` | Cron: daily overdue invoice check |
| Vue component | `Components/NotificationBell.vue` | Bell icon with dropdown, 30s polling |
| Vue page | `Config/Notifications/Index.vue` | Admin: toggle notification types per user |
| Routes | `routes/web/notifications.php` | 9 routes |

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
DELETE /config/notifications/user/{user}       config.notifications.delete-user
```

---

## Six notification types

| Type key | Trigger | Channels | Who receives it |
|----------|---------|----------|----------------|
| `ticket.needs-catalog` | Ticket status → `Catálogo` | mail + database | All subscribers |
| `catalog.approved` | BudgetCatalog approved | mail + database | **Only the ticket's seller**, if they have the setting active |
| `catalog.needs-update` | Edited budget re-sent to costs with an existing catalog (status → `pending_update`) | mail + database | **Active users with `costs.receive-catalog-update-notifications`** (not configurable) |
| `ticket.needs-invoice` | Ticket status → `Finalizado` | mail + database | All subscribers |
| `invoice.overdue` | Cron detects due date reached | mail + database | All subscribers |
| `deposit.pending-approval` | Deposit created | database only | All subscribers |

---

## Dispatch architecture

### `DispatchNotificationAction`
Centralized class injected into:
- `Ticket` model (booted::updated) — for `ticketNeedsInvoice` (status → Finalizado)
- `CostController::approveCatalog()` — for `catalogApproved` (when catalog is approved)
- `BudgetController::update()` — for `catalogNeedsUpdate` (when an edited budget re-sent to costs invalidates its catalog)
- `CheckOverdueInvoices` command — for `invoiceOverdue`
- Controllers that create deposits — for `depositPendingApproval`

### Special case: `catalogApproved`
Unlike other notifications that go to all subscribers, catalog approval only notifies the **ticket's seller** — and only if they have the `catalog.approved` notification setting active. This uses an explicit `NotificationSetting` check rather than the `notifySubscribers` helper. The notification fires when a user with `costs.approve` permission approves the catalog, not when the catalog is created.

### Special case: `catalogNeedsUpdate`
This notification targets the costs team directly and has **no subscriber configuration** — it isn't part of `NotificationSetting::TYPES`. Recipients are resolved from permissions: **active users holding the dedicated `costs.receive-catalog-update-notifications` permission** (granted directly or through a role, category `Costos`). It uses `NotificationService::notifyUsersWithPermissions()` instead of `notifySubscribers()`.

### `NotificationService::notifySubscribers(string $type, Notification $notification)`
- Queries `NotificationSetting::subscribersFor($type)` — returns users with active setting and valid email
- Sends the notification to each subscriber via Laravel's `Notification::send()`

### `NotificationService::notifyUsersWithPermissions(array $permissions, Notification $notification)`
- Queries active users with a valid email that hold **any** of the given permissions, via Spatie's `permission()` scope (includes permissions granted through roles)
- Sends the notification to each user

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

- Card-based grid layout with user cards showing name, email, role, and active notification count
- Click a card to open a dialog with checkboxes for each notification type
- **Filters**: search by name/email (debounced 300ms) and filter by Spatie role
- **Per-user reset**: trash icon on each user card deletes all their settings (with confirmation)
- Legacy `catalog.created` type is accepted and auto-mapped to `catalog.approved` on save

---

## Dependencies on other modules

- **Users** (`03`): `NotificationSetting` is per-user; subscribers filtered by valid email
- **Tickets** (`06`): `ticketNeedsCatalog`, `ticketNeedsInvoice` triggered by status changes
- **Budgets** (`07`): Catalog approval triggers `catalogApproved`
- **Costs** (`08`): Edited budget re-sent to costs invalidates the catalog (`pending_update`) and triggers `catalogNeedsUpdate`
- **Deposits** (`11`): Deposit creation triggers `depositPendingApproval`
- **Invoices** (`12`): Cron command triggers `invoiceOverdue`

---

## Known limitations / cautions

- **`catalogApproved` is an exception:** Unlike the subscriber-based types, it doesn't use `notifySubscribers` — it manually checks the seller's setting. If you want to change who receives catalog approval notifications, edit `DispatchNotificationAction::catalogApproved`.
- **`catalog.needs-update` is permission-based, not configurable:** It isn't listed in `NotificationSetting::TYPES`, so it never appears in the notification settings page. Recipients are users with the dedicated `costs.receive-catalog-update-notifications` permission (via role or direct assignment); to change the permission used, edit `DispatchNotificationAction::catalogNeedsUpdate`.
- **No notification for calendar events:** Participant invitations exist in the data model but aren't wired to notifications
- **30-second polling:** The bell polls every 30s — if many users are active, this generates constant requests. Consider WebSockets (Laravel Reverb/Echo) for scale.
- **`deposit.pending-approval` is database-only:** No email is sent for deposit approvals — admins must check the notification bell
- **Notification settings page is admin-only:** Regular users cannot configure their own notification preferences from the profile page
- **No push notifications:** Only mail + database channels — no mobile push
- **`ticket.needs-catalog` is no longer triggered by status change:** This notification type still exists in the system but is not currently dispatched by any automatic flow (was previously triggered on `Catálogo` status change; now catalogs go through `Pendiente de aprobación` → approval flow)
