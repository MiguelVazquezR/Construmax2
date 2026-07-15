# Construmax2 ERP — 10: Calendar Module

> **Purpose:** Shared team calendar with event CRUD, participant management, and status responses.  
> **Context file covers:** Calendar events, participant invitations, completion tracking.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `CalendarController.php` | CRUD + respond + toggle-complete |
| Model | `Calendar.php` | BelongsTo User (creator); BelongsToMany User (participants with pivot status) |
| Vue pages | `Calendar/Index.vue` | Full calendar UI using Element Plus Calendar |
| Routes | `routes/web/calendar.php` | 7 routes |

---

## Routes

```
GET    /calendar/overview                calendar.overview
GET    /calendar                         calendar.index
POST   /calendar                         calendar.store
PUT    /calendar/{calendar}              calendar.update
DELETE /calendar/{calendar}              calendar.destroy
PUT    /calendar/{calendar}/respond      calendar.respond
PUT    /calendar/{calendar}/toggle-complete  calendar.toggle-complete
```

---

## Data model

### Calendar
- `user_id`: Creator
- `type`: Reunión, Tarea, Llamada, Recordatorio, Evento
- `title`, `description`
- `start_time`, `end_time` (datetime)
- `is_completed`: boolean

### calendar_participants (pivot)
- Many-to-many: Calendar ↔ User
- Pivot columns: `status` (pending/accepted/declined), `rejection_reason`

---

## Vue page (`Calendar/Index.vue`)

- Element Plus Calendar component
- Click a date → create event dialog
- Click an event → edit/delete dialog
- Event type selector
- Participant multi-select from users list

---

## Dependencies on other modules

- **Users** (`03`): Creator and participants
- **Dashboard** (`04`): My Day section pulls today's calendar events

---

## Known limitations / cautions

- **No recurring events:** All events are one-time only
- **No notifications for invitations:** Participant invitations exist in the data model but there's no notification dispatch for them
- **Simple UI:** The calendar is a basic CRUD view — no drag-and-drop rescheduling, no timezone handling, no iCal export
