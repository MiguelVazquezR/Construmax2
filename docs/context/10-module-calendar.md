# Construmax2 ERP — 10: Calendar Module

> **Purpose:** Shared team calendar with dual-mode operation: Personal Calendar (events, tasks, invitations) and Field Work Calendar (on-site service scheduling with task timestamp automation).  
> **Context file covers:** Calendar events, participant invitations, completion tracking, field work schedules, calendar views (Day/Week/Month).

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `CalendarController.php` | Personal calendar CRUD + respond + toggle-complete |
| Controller | `FieldWorkScheduleController.php` | Field work schedule CRUD + available tickets API + events API |
| Model | `Calendar.php` | BelongsTo User (creator); BelongsToMany User (participants with pivot status) |
| Model | `FieldWorkSchedule.php` | BelongsTo Ticket; BelongsTo User (creator); unique per ticket |
| Service | `FieldWorkScheduleService.php` | Task timestamp sync logic (start/end → task dates) |
| Actions | `FieldWork/Create|Update|DeleteFieldWorkScheduleAction.php` | Orchestrate schedule CRUD + task sync |
| Requests | `FieldWork/Store|UpdateFieldWorkScheduleRequest.php` | Validation (auth via route middleware) |
| Vue pages | `Calendar/Index.vue` | Dual-mode UI: mode selector, Day/Week/Month views, modals |
| Vue partials | `Calendar/Partials/CalendarModeSelector.vue` | Toggle between Personal / Field Work + Day/Week/Month |
| Vue partials | `Calendar/Partials/FieldWorkFormModal.vue` | Create/edit/delete field work schedules with ticket details |
| Vue components | `MyComponents/DayView.vue` | 24-hour day grid with collision-aware event layout |
| Vue components | `MyComponents/WeekView.vue` | 7-day week grid with multi-day banner and per-day collision |
| Routes | `routes/web/calendar.php` | 7 routes (personal calendar) |
| Routes | `routes/web/field-work.php` | 5 routes (field work schedules) |

---

## Routes

### Personal Calendar
```
GET    /calendar/overview                calendar.overview
GET    /calendar                         calendar.index
POST   /calendar                         calendar.store
PUT    /calendar/{calendar}              calendar.update
DELETE /calendar/{calendar}              calendar.destroy
PUT    /calendar/{calendar}/respond      calendar.respond
PUT    /calendar/{calendar}/toggle-complete  calendar.toggle-complete
```

### Field Work Schedules
```
GET    /field-work/available-tickets     field-work.available-tickets
GET    /field-work/events                field-work.events
POST   /field-work                       field-work.store
PUT    /field-work/{schedule}            field-work.update
DELETE /field-work/{schedule}            field-work.destroy
```

---

## Data model

### Calendar (personal events)
- `user_id`: Creator
- `type`: Reunión, Tarea, Llamada, Recordatorio, Evento
- `title`, `description`
- `start_time`, `end_time` (datetime)
- `is_completed`: boolean

### calendar_participants (pivot)
- Many-to-many: Calendar ↔ User
- Pivot columns: `status` (Pendiente/Aceptado/Rechazado), `rejection_reason`

### field_work_schedules
- `ticket_id`: FK → tickets.id, **unique** (one schedule per ticket)
- `user_id`: FK → users.id, creator
- `start_time`, `end_time` (datetime)
- `color`: hex color string (default `#409EFF`)
- `notes`: text, nullable

---

## Vue page (`Calendar/Index.vue`)

### Dual-mode architecture
The calendar page has two operating modes toggled via `CalendarModeSelector`:

1. **Personal Calendar** — Existing functionality: create/edit/delete personal events (Reunión, Tarea, Llamada, Recordatorio, Evento), invite participants, accept/decline invitations, toggle completion.

2. **Field Work Calendar** — Schedule on-site field operations for service tickets. Only visible to users with `tickets.calendar.view` permission.

### Three view layouts
- **Day View** (`DayView.vue`): 24-hour vertical grid with time labels, collision-aware event positioning using absolute layout, all-day section, current-time indicator
- **Week View** (`WeekView.vue`): 8-column CSS Grid (time gutter + 7 days), sticky headers, collapsible multi-day banner with overflow indicators (←/→ arrows), per-day collision layout
- **Month View**: Element Plus `<el-calendar>` with inline event chips

### Field Work Form Modal
- **Create mode**: Filterable ticket selector (only tickets in `Proceso de ejecución` status that are not already scheduled), auto-populated start/end from first/last task dates, 25-color palette + custom color picker, notes
- **Edit mode (owner)**: Read-only ticket display, editable ticket fields (name, report number, service type — requires `tickets.edit` permission), service type dropdown from `/service-types` API, link to full ticket details (`tickets.show`). Ticket field updates are sent via axios (JSON) before the schedule form submission to avoid request cancellation.
- **Edit mode (read-only)**: Non-owners see a read-only view — all form fields disabled, no "Editar datos del ticket" section, no delete/save buttons (only "Cerrar"). Dialog title changes to "Detalles de agenda de trabajo en campo".
- **Delete**: Confirmation dialog (`ElMessageBox.confirm`), then removes schedule and clears associated task timestamps. Only available to the schedule creator.
- **Save button**: Shows instant loading state (`saving` ref) while ticket field updates are in-flight, then `form.processing` during the schedule update.

### Event color rendering
All three views render events with the user-selected color via dynamic inline styles (`backgroundColor`, `borderLeftColor`, `color`). Events linked to tickets with status `Ejecutado`, `Facturado`, or `Pagado` are rendered as completed: muted gray palette (`#9ca3af` border, `#6b7280` text, `#9ca3af20` background) with reduced opacity.

### Month view multi-day events
Events spanning multiple days appear on every day they cover, not just the first day. The `getEventsForDate()` filter checks `start_time <= date <= end_time` inclusively.

### Week view multi-day banner
Only events that overlap the currently displayed week are shown in the multi-day banner. Events entirely before or after the week are excluded. The `←`/`→` overflow arrows appear only for events partially extending beyond the visible week.

### Field work filters
When in Field Work mode, a 3-column filter bar appears below the mode selector:
- **Technician** (`technician_id`): Filters schedules whose ticket includes the selected technician (checks `technicians` and `assistant_technicians` JSON arrays)
- **Branch** (`branch_id`): Filters schedules by ticket's `customer_branch_id`; dropdown is filterable/searchable
- **Internal/External** (`is_internal`): Filters schedules based on whether any technician on the ticket has `is_internal = true/false` in their Technician record

All filters are clearable (reset to "show all"). The `events` API endpoint accepts these as query params. An event count indicator (`"X eventos"`) appears below the filters and updates reactively as filters change. The `CalendarController@index` passes `technicians` (users with Technician records) and `branches` (all customer branches) as page props for the dropdowns.

---

## Field Work automation

When a field work schedule is created or updated, the `FieldWorkScheduleService` syncs the associated ticket's task timestamps:
- **Tasks 1 to N-1**: `start_date` = schedule's `start_time`
- **Task N (last)**: `start_date` = schedule's `start_time`, `due_date` = schedule's `end_time`

When a schedule is deleted, all task timestamps are cleared (set to null).

---

## Permissions

| Permission | Scope |
|-----------|-------|
| `tickets.calendar.view` | View the Field Work calendar mode and its events |
| `tickets.calendar.create` | Create field work schedules only. Editing and deleting are strictly owner-only (no permission bypass). |
| `tickets.edit` | Edit ticket fields (name, report number, service type) from within the field work edit modal (only when user is the schedule owner) |

---

## Dependencies on other modules

- **Users** (`03`): Creator and participants (personal calendar); schedule creator (field work)
- **Dashboard** (`04`): My Day section pulls today's calendar events
- **Tickets** (`06`): Field work schedules are linked 1:1 to tickets; task timestamps are auto-synced
- **Service Types** (`06`): Service type dropdown in edit modal fetches from service-types API
- **Technicians** (`09`): `is_internal` filter uses Technician records to resolve internal/external status; technician filter dropdown lists all users with Technician records
- **Customer Branches** (`05`): Branch filter dropdown lists all customer branches for schedule filtering

---

## Known limitations / cautions

- **No recurring events:** All events are one-time only
- **No notifications for invitations:** Participant invitations exist in the data model but there's no notification dispatch for them
- **Field work ownership:** Editing and deleting schedules is strictly owner-only — even users with `tickets.calendar.create` cannot modify other users' schedules. Backend enforces this in `update()` and `destroy()`; frontend shows read-only mode for non-owners.
- **Completed events:** Events linked to tickets with status `Ejecutado`, `Facturado`, or `Pagado` are visually distinguished as completed (muted gray) in all views. The `_completed` flag is added client-side in `activeEvents` computed and the `ticket_status` field comes from the events API.
- **Instant save feedback:** The "Guardar cambios" button shows a loading spinner immediately on click (before any async work starts) via a `saving` ref, avoiding perceived unresponsiveness during ticket field updates.
- **One schedule per ticket:** The `ticket_id` unique constraint prevents scheduling the same ticket twice. To reschedule, edit the existing schedule.
- **Task sync is one-way:** Updating tasks manually does not update the schedule; only schedule changes propagate to tasks.
- **Filter scope:** Filters apply server-side via the `events` API. The `is_internal` filter resolves all technician user IDs with the matching `is_internal` status, then filters schedules whose tickets involve any of those technicians. An empty technician set for the selected `is_internal` value returns zero results.
- **Filter data sources:** The technician dropdown lists all users with a `Technician` record. The branch dropdown lists all `CustomerBranch` records (not limited to branches with existing schedules).
