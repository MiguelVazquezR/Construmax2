# Construmax2 ERP — 04: Dashboard & Tutorials

> **Business purpose:** Landing page after login with KPIs, analytics dashboard with charts, my-day planning, and video tutorials.  
> **Context file covers:** Dashboard, TicketsDashboard (Analytics), Tutorials.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `DashboardController.php` | Main dashboard data |
| Controller | `TicketAnalyticsController.php` | Analytics dashboard data |
| Controller | `TutorialController.php` | Tutorial listing |
| Vue pages | `Dashboard.vue` | Main landing page |
| Vue pages | `TicketsDashboard.vue` | Full analytics dashboard |
| Vue pages | `Tutorials/Index.vue` | Video tutorial gallery |
| Vue components | `Components/Dashboard/` | 6 dashboard sub-components |
| Routes | `routes/web.php` | `/dashboard` |

---

## Main dashboard (`Dashboard.vue`)

### Data provided
- `my_day`: Current user's day schedule (calendar events + pending tasks)
- `kpis`: Aggregated KPIs across CRM, Operations, Costs, Invoicing

### KPI cards displayed
| Category | Metrics |
|----------|---------|
| **CRM** | Monthly sales amount, budgets closed, new customers this month |
| **Operations** | Active tickets, overdue tickets |
| **Costs** | Budgets needing catalog |
| **Invoicing** | Budgets with executed-status tickets |
| **Deposits** | Pending deposits count, today's scheduled deposits, list of today's deposits with status tags |

### Quick-access links
New ticket, new budget, new customer

### My Day section
- Lists today's calendar events
- Lists pending tasks assigned to the user from tickets

---

## Analytics dashboard (`TicketsDashboard.vue`)

### Filters
- Date range (start/end)
- Customer (select)
- Seller (select)
- MXN/USD currency toggle

### Charts & components
| Component | Chart type | What it shows |
|-----------|-----------|---------------|
| `CRMRevenue.vue` | Bar chart + KPI cards | Monthly sales, budgets closed, conversion rate, average ticket. Top sellers table, top customers table |
| `GlobalState.vue` | KPI cards | Total backlog, pending tasks, technicians with active tickets, regions with active tickets |
| `TicketsPerformance.vue` | Area charts + KPI cards | Tickets created/completed/overdue/in-progress. Timeline of scheduled vs created |
| `RegionDistribution.vue` | ECharts Mexico map | Ticket count per state/region |
| `InvoiceStatus.vue` | Donut chart | Paid vs pending vs overdue invoices |
| `TechnicianPayments.vue` | Tables + KPIs | Completed-unpaid technicians, pending-tasks technicians |

### Data flow
All data is computed server-side in `TicketAnalyticsController@index` and passed as props. Charts use `vue3-apexcharts` (bar, area, donut) and `vue-echarts` (map).

---

## Tutorials (`Tutorials/Index.vue`)

### What it is
A hardcoded array of 8 video tutorial entries displayed as a card grid. Each card shows a thumbnail, title, description, and duration badge. Clicking opens a modal with an embedded video player.

### Video data
- Stored in `TutorialController@index` as a PHP array (not from database)
- Includes: `title`, `description`, `duration`, `filename`, `thumbnail`

---

## Dependencies on other modules

- **Tickets** (`06`): Dashboard shows ticket counts, overdue tickets, task counts
- **Budgets** (`07`): KPI cards show budget data, analytics show income by budget
- **Customers** (`05`): Customer filter in analytics
- **Users** (`03`): Seller filter in analytics
- **Calendar** (`10`): My Day section shows calendar events
- **Invoices** (`12`): Invoice status donut chart
- **Deposits** (`11`): Dashboard shows pending deposits count and today's scheduled deposits

---

## Known limitations / cautions

- **Main dashboard KPIs are real-time computed** — may become slow with large datasets. No caching layer.
- **Analytics data is all computed in `TicketAnalyticsController`** — heavy method with multiple private helpers. Consider extracting to a dedicated AnalyticsService.
- **Tutorials are hardcoded** — to add/change videos, edit `TutorialController@index` directly. No admin UI.
- **ECharts Mexico map** requires the Mexico GeoJSON to be loaded client-side; check `RegionDistribution.vue` for the map registration logic.
- **Currency toggle** in analytics is client-side only — data is pre-computed in both currencies by the controller.
