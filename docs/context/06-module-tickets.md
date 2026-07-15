# Construmax2 ERP — 06: Tickets Module

> **Business purpose:** Service work orders — the central entity linking customers, technicians, budgets, and tasks.  
> **Context file covers:** Tickets CRUD, Ticket Tasks, Task Templates, Service Types, Evidence, Kanban, Public Task View.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `TicketController.php` | Full CRUD + status, technicians, evidence, field updates |
| Controller | `TicketTaskController.php` | Task CRUD + public signed-URL task views |
| Controller | `TaskTemplateController.php` | Task template CRUD (JSON API) |
| Controller | `ServiceTypeController.php` | Service type CRUD (JSON API) |
| Model | `Ticket.php` | Implements HasMedia; JSON arrays for technicians; auto-status from tasks |
| Model | `TicketTask.php` | Implements HasMedia; task lifecycle |
| Model | `TaskTemplate.php` | Reusable task checklist templates |
| Model | `TaskTemplateItem.php` | Line items in templates |
| Model | `ServiceType.php` | Simple taxonomy |
| Vue pages | `Tickets/` | Index, Create, Edit, Show, EvidenceTemplate, PublicTask |
| Vue partials | `Tickets/Partials/` | 10 sub-components |
| Routes | `routes/web/tickets.php` | 29 routes (5 public + 24 auth) |

---

## Ticket lifecycle (status flow)

```
Borrador ──▶ Levantamiento ──▶ Catálogo ──▶ Proceso de ejecución ──▶ Ejecutado ──▶ Facturado ──▶ Pagado
                                                                        │
                                                                        ▼
                                                                    Cancelado / Perdido
```

**Auto-events on status change:**
- `Catálogo` → dispatches `ticketNeedsCatalog` notification
- `Finalizado` (Ejecutado) → dispatches `ticketNeedsInvoice` notification

**Priority levels:** Baja, Media, Alta, Urgente

---

## Routes summary

### Tickets (auth)
```
GET    /tickets/dashboard              tickets.dashboard
GET    /tickets                        tickets.index
GET    /tickets/create                 tickets.create
POST   /tickets                        tickets.store
GET    /tickets/{ticket}               tickets.show
GET    /tickets/{ticket}/edit          tickets.edit
PUT    /tickets/{ticket}               tickets.update
DELETE /tickets/{ticket}               tickets.destroy
POST   /budgets/{budget}/ticket-auto   tickets.store-from-budget
PUT    /tickets/{ticket}/status        tickets.update-status
PUT    /tickets/{ticket}/technicians   tickets.update-technicians
PUT    /tickets/{ticket}/important-note  tickets.update-important-note
PUT    /tickets/{ticket}/report-number   tickets.update-report-number
PUT    /tickets/{ticket}/update-field    tickets.update-field
```

### Tasks (auth)
```
POST   /tickets/{ticket}/tasks            tickets.tasks.store
PUT    /tickets/tasks/{task}              tickets.tasks.update
DELETE /tickets/tasks/{task}              tickets.tasks.destroy
PUT    /tickets/tasks/{task}/toggle       tickets.tasks.toggle
PUT    /tickets/tasks/{task}/notes        tickets.tasks.notes
POST   /tickets/tasks/{task}/evidence     tickets.tasks.evidence.store
POST   /tickets/tasks/{task}/evidence/reorder  tickets.tasks.evidence.reorder
```

### Evidence (auth)
```
POST /tickets/{ticket}/evidence           tickets.evidence.store
GET  /tickets/{ticket}/evidence-template  tickets.evidence-template
DELETE /tickets/evidence/{media}          tickets.evidence.destroy
```

### Public routes (signed URLs, no auth)
```
GET    /t/job-order/{ticket}/{user}        tickets.public.job-order
PUT    /t/track/{task}/toggle              tasks.public.toggle
POST   /t/track/{task}/evidence            tasks.public.evidence
DELETE /t/track/evidence/{media}           tasks.public.evidence.destroy
PUT    /t/track/{task}/notes               tasks.public.notes
```

### Task Templates
```
GET    /task-templates                          task-templates.index
POST   /task-templates                          task-templates.store
PUT    /task-templates/{taskTemplate}           task-templates.update
PUT    /task-templates/{taskTemplate}/toggle-status  task-templates.toggle-status
DELETE /task-templates/{taskTemplate}           task-templates.destroy
```

### Service Types
```
GET    /service-types                   service-types.index
POST   /service-types                   service-types.store
PUT    /service-types/{serviceType}     service-types.update
DELETE /service-types/{serviceType}     service-types.destroy
```

---

## Ticket data model

### Key fields
- `technicians` (json): Array of lead technician user IDs
- `assistant_technicians` (json): Array of assistant technician user IDs (level: "Auxiliar/Ayudante")
- `seller_id`: The user who sold/created the ticket
- `service_type`: Free-text string (pre-populated from ServiceType suggestions)

### Computed properties
- `progress`: `round(completedTasks / totalTasks * 100)`
- `folio`: `#ID-REGION-COUNTRY` format

### TicketTask
- Assigned to a `user_id` (technician)
- Status: `Pendiente` → `En proceso` → `Completada`
- Has `technician_notes` field
- Has media collection `task_evidence` (ordered by `order_column`)

---

## Key behaviors

### Task generation from templates
When creating/editing a ticket, selecting a task template generates individual `TicketTask` records per technician × template item. Lead technician gets all items; assistant technicians also get copies.

### Technician reassignment
`updateTechnicians` and `reassignTechnicianData` handle moving tasks and technician payments from removed technicians to newly assigned ones.

### Status auto-calculation
`updateStatusBasedOnTasks()` on the Ticket model recalculates ticket status from task completion — called after any task change.

### Overlap detection
`TicketTaskController@checkForOverlaps` detects scheduling conflicts between tasks and returns a warning string.

### Public technician view (`PublicTask.vue`)
- Accessible via signed URL (`/t/job-order/{ticket}/{user}`)
- Shows safety notice, customer/location info, task checklist, evidence upload
- No auth required — uses Laravel signed URLs

### Evidence upload
- Images are optimized via `ImageOptimizerService` (GD, 1920px max, 75% quality)
- Videos passed through as-is
- Supports reordering via `order_column`

### Kanban board (`TicketKanban.vue`)
- 10 columns matching status flow
- Drag-and-drop with optimistic UI
- Async validation (e.g., moving to "Catálogo" requires budget to exist)

---

## Vue pages

### `Tickets/Index.vue`
- Toggle between list view (`TicketList.vue`) and kanban view (`TicketKanban.vue`), persisted in localStorage
- Extensive filters: folio, customer, region, priority, technician, seller, catalog status, sort

### `TicketForm.vue` (core)
- Customer → contact/branch cascading selectors
- Service type with inline CRUD modal
- Seller dropdown (users)
- Technician assignment: lead (Encargado) + assistant (Auxiliar/Ayudante)
- Task template selection (opens `TaskTemplateModal`)
- Quick branch creation (`QuickBranchModal`)
- Priority, scheduled dates, instructions, file upload

### `TicketTasks.vue`
- Drag-and-drop task list (`vuedraggable`)
- Inline editing, status changes, evidence upload per task
- Technician reassignment
- Payment tracking for external technicians
- Shareable public task link generation
- Task template application to existing ticket

### `TicketInfo.vue`
- Operational details display
- Print buttons for cost catalog and evidence template
- Evidence upload section

### `TicketTimeline.vue`
- Vertical timeline + Gantt chart (ApexCharts)
- Shows all ticket events chronologically

---

## Dependencies on other modules

- **Customers** (`05`): Customer/Branch/Contact selection
- **Technicians** (`09`): Technician assignment (JSON arrays of user IDs)
- **Budgets** (`07`): 1:1 relationship; ticket status changes trigger budget workflows
- **Costs** (`08`): Cost catalog is created for ticket's budget
- **Invoices** (`12`): Invoice upload triggers ticket status to "Facturado"
- **Notifications** (`13`): Status changes dispatch notifications
- **Users** (`03`): Seller assignment, task assignment
- **Deposits** (`11`): Deposits reference tickets

---

## Known limitations / cautions

- **Technicians stored as JSON:** `technicians` and `assistant_technicians` are JSON columns, not FK relationships — no referential integrity, harder to query
- **Service types as free text:** `service_type` on tickets is a string, not an FK to `service_types` — the ServiceType model is just a suggestion list
- **Status flow is linear but not enforced:** The Kanban allows drag-and-drop to any column; validation happens async
- **Ticket-to-budget is 1:1 only:** Each ticket can have at most one budget; there's no support for multiple revisions/proposals
- **Public routes use signed URLs:** These expire based on Laravel's signed URL configuration — long-lived technician links may break
- **Task template application on edit:** Applying a template to an existing ticket may duplicate tasks if not handled carefully — check the controller logic
