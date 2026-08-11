# Construmax2 ERP — 08: Costs Module

> **Business purpose:** Versioned cost catalogs for budgets — manage materials and labor line items, calculate totals with IVA, Empeño Fácil special calculations, and print-friendly budget layouts.  
> **Context file covers:** Cost catalog CRUD, materials/labor tables, print views, Empeño Fácil mode.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `CostController.php` | List, show, catalog CRUD, approval, print views |
| Service | `CostService.php` | Budget listing for costing; catalog details including technicians, task evidence, ticket media, survey images, concepts |
| Model | `BudgetCatalog.php` | Versioned catalog header |
| Model | `BudgetCatalogItem.php` | Line items (materials + labor) |
| Vue pages | `Costs/Index.vue` | Catalog listing |
| Vue pages | `Costs/Show.vue` | Core costs editor |
| Vue pages | `Costs/Print.vue` | Standard print layout |
| Vue pages | `Costs/PrintEmpenoFacil.vue` | Empeño Fácil print layout |
| Vue components | `Costs/MaterialsTable.vue` | Editable materials table |
| Vue components | `Costs/LaborTable.vue` | Editable labor table |
| Vue components | `Costs/EmpenoFacilTotals.vue` | Empeño Fácil totals card |
| Composables | `useCostsHelpers.js` | Currency formatting, clipboard |
| Routes | `routes/web/costs.php` | 6 routes |

---

## Routes

```
GET  /costs                                         costs.index
GET  /costs/{budget}                                costs.show
GET  /costs/{budget}/print                          costs.print
GET  /costs/{budget}/print-empeno-facil             costs.print-empeno-facil
POST /costs/{budget}/catalog                        costs.store-catalog
POST /costs/{budget}/catalog/{catalog}/approve      costs.approve-catalog
```

---

## Data model

### BudgetCatalog
- `budget_id`: FK to budgets
- `version`: integer, auto-incremented per budget
- Financials: `subtotal`, `iva`, `total`
- Empeño Fácil fields: `non_installation_labor` (12% overhead), `labor_utility` (18% utility)
- Approval: `status` (`pending_approval` | `approved`), `approved_by` (FK to users), `approved_at` (timestamp)

### BudgetCatalogItem
- `type`: `material` or `labor`
- Material fields: `description`, `unit`, `quantity`, `unit_price`, `total`
- Labor fields: `description`, `technician`, `hours`, `rate`, `total`

---

## Key behaviors

### Catalog versioning
- Each save creates a new version (incremented from `MAX(version) + 1`)
- `Budget.latestCatalog` returns the highest-version catalog via `latestOfMany('version')`
- Historical catalogs are preserved, not overwritten

### Catalog approval flow
- New catalogs are created with `status = pending_approval`
- On catalog save, the ticket status changes to `Pendiente de aprobación`
- Users with `costs.approve` permission can approve from the costs list or detail view
- On approval: catalog status → `approved`, ticket status → `Catálogo`, notification sent to seller
- There is no rejection flow — if adjustments are needed, create a new version (which resets to pending)
- Print views show a large "ESPERANDO APROBACIÓN" watermark overlay when catalog is not approved
- Filters in costs index default to `Pendientes de aprobación`; other options: `Aprobados`, `Todos`, `Sin catálogo`

### Empeño Fácil mode
- Activated when the customer ID is 2 (hardcoded **on the frontend** — `Costs/Show.vue` checks `customer.id === 2`)
- Auto-calculates:
  - **Non-installation labor:** 12% of materials subtotal
  - **Labor utility:** 18% of labor subtotal
- Separate print layout (`PrintEmpenoFacil.vue`): materials section, labor section, bank details, copy-to-clipboard helpers

### IVA handling
- Toggleable client-side (`includeIva`)
- When enabled, `iva = subtotal * 0.16` and `total = subtotal + iva`
- When disabled, `total = subtotal`

---

## Vue pages

### `Costs/Index.vue`
- Lists budgets with catalog info
- Filters: search, approval status (Pendientes de aprobación/Aprobados/Todos/Sin catálogo), branch
- Default filter: Pendientes de aprobación
- Each row shows: ticket folio (clickable link to ticket if user has `tickets.index` permission), customer, branch, catalog status + approved by, total
- Approve button for users with `costs.approve` permission (only on pending catalogs)
- Info alert explaining the no-rejection flow (create new version instead)

### `Costs/Show.vue` (core editor)
- Two editable tables: `MaterialsTable` + `LaborTable`
- Inline editing: description, unit, quantity, unit price → auto-calculates total
- Add/delete rows
- IVA toggle
- Empeño Fácil mode: shows `EmpenoFacilTotals` card with auto-calculations
- Report number editing (updates ticket's `report_number`)
- Save button creates a new catalog version (pending approval, ticket → Pendiente de aprobación)
- Approve button in header for users with `costs.approve` (only on unapproved catalogs)
- Approval status badge with approver name

### `Costs/Print.vue`
- Print-friendly layout
- Corporate header (logo, branch, city, folio)
- Concepts table with totals
- Bank info section
- PDF instructions dialog
- **Approval watermark**: large red "ESPERANDO APROBACIÓN" overlay when catalog is not yet approved

### `Costs/PrintEmpenoFacil.vue`
- Empeño Fácil specific print layout
- Separates materials and labor sections with subtotals
- Custom calculations displayed
- Bank details with copy-to-clipboard
- **Approval watermark**: same overlay as Print.vue when catalog is not yet approved

---

## Dependencies on other modules

- **Budgets** (`07`): Catalogs belong to budgets; budget's `total_cost` falls back to catalog total; `Budgets/Show.vue` shows approval status tag
- **Tickets** (`06`): Ticket folio, customer, branch info displayed; report number editing updates ticket; `TicketInfo.vue` shows catalog approval status; ticket status changes to `Pendiente de aprobación` on catalog save
- **Customers** (`05`): Customer ID #2 is the Empeño Fácil customer
- **Notifications** (`13`): `CatalogApproved` notification dispatched on catalog approval

---

## Service details

### `CostService.getBudgetCatalogDetails()`
Returns a comprehensive array including:
- Basic budget info: `id`, `currency`, `exchange_rate`, `description`, `subtotal`
- Ticket data: `folio`, `name`, `report_number`, `service_type`, `scheduled_start`, `scheduled_end`, `instructions`, `important_note`
- Nested relationships: `customer`, `contact`, `branch`, `seller`
- **Technicians:** Resolves user IDs from `ticket.technicians` and `ticket.assistant_technicians` arrays, loads `User` with `technician` relation, returns `id`, `name`, `email`, `profile_photo_url`, `phone`, `level`, `status`, `rating_avg`
- **Task evidence:** Collects all media across all ticket tasks, sorted by `created_at` desc
- **Ticket media:** All media uploaded directly to the ticket
- **Concepts:** Budget concepts with `concept` and `amount`
- **Survey images:** From the budget's `survey_images` media collection
- **Catalogs:** All historical catalogs with items, plus `latestCatalog` singled out
- **Catalog items:** `id`, `type`, `description`, `unit`, `technician`, `hours`, `rate`, `quantity`, `unit_price`, `total`

---

## Known limitations / cautions

- **Empeño Fácil is hardcoded to customer ID 2 (frontend):** `Costs/Show.vue` checks `customer.id === 2`. If the customer ID changes or different customers need this format, the logic needs refactoring
- **No item-level permissions:** All catalog editing is gated by a single `canCreateCatalog` prop
- **IVA calculation is client-side only:** The 16% IVA is hardcoded in JS — if the rate changes, multiple files need updates
- **Catalog versioning is append-only:** There's no way to delete a catalog version or revert to a previous one from the UI
- **Report number lives on the ticket:** Editing it from the costs page is a convenience feature — be aware of potential race conditions if edited from multiple places
- **No rejection flow:** If a catalog needs changes, users must create a new version — the previous version remains in history but is superseded
- **Approval is all-or-nothing:** There is no partial approval or per-item approval — the entire catalog is approved at once
- **`status` field must be present in serialized catalog data:** Views like `Budgets/Show.vue` and `TicketInfo.vue` read `latest_catalog.status` directly — ensure the BudgetCatalog model's `status` column is included in API responses

---

## Special costs authorization (sub-module)

> **Business purpose:** Some catalogs require special review from Dirección (Management) before approval. These catalogs are transferred out of normal "Costos" into "Costos especiales", where only authorized users can approve them or create new versions.

### New fields on `budget_catalogs`

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `needs_special_authorization` | boolean | `false` | Flag marking the catalog for special authorization |
| `transfer_notes` | text | nullable | Notes provided at transfer time explaining why Dirección review is needed |

### New permissions

| Permission | Category | Purpose |
|-----------|---------|---------|
| `costs.transfer` | Costos | Transfer catalogs from Costs → Costos especiales |
| `special-costs.index` | Costos especiales | View the special costs module (menu, list, detail) |
| `special-costs.approve` | Costos especiales | Approve catalogs in the special costs module |
| `special-costs.create-version` | Costos especiales | Create new catalog versions from the special costs module |

### New routes

| Method | URI | Name |
|--------|-----|------|
| POST | `/costs/{budget}/catalog/{catalog}/transfer-to-special` | `costs.transfer-to-special` |
| GET | `/special-costs` | `special-costs.index` |
| GET | `/special-costs/{catalog}` | `special-costs.show` |
| POST | `/special-costs/{catalog}/version` | `special-costs.store-catalog` |
| POST | `/special-costs/{catalog}/approve` | `special-costs.approve-catalog` |

### New key files

| Layer | File | Purpose |
|-------|------|---------|
| Service | `SpecialCosts/SpecialCostService.php` | List pending special catalogs, load catalog details |
| Controller | `SpecialCostController.php` | index, show, storeCatalog, approveCatalog |
| Vue pages | `SpecialCosts/Index.vue` | Special catalog list (only pending) |
| Vue pages | `SpecialCosts/Show.vue` | Special catalog detail with transfer notes + approval |
| Routes | `routes/web/special-costs.php` | 4 routes |

### Transfer flow

1. User with `costs.transfer` clicks "Enviar a aprobación" in Costs/Index or Costs/Show
2. Modal requires transfer notes (mandatory, max 2000 chars)
3. On submit: `BudgetCatalog.needs_special_authorization = true`, `transfer_notes` saved
4. **Ticket status does NOT change** — stays in its current status (normally "Pendiente de aprobación")
5. Costs/Index and Costs/Show show an "En revisión por Dirección" badge instead of Approve/Transfer buttons when the catalog was transferred

### Special costs list (`SpecialCosts/Index.vue`)

- Shows only catalogs with `needs_special_authorization = true AND status = pending_approval`
- Once approved, catalogs disappear from this list
- Columns: folio, project, customer, branch, version, status, total, transfer date
- Filters: search, branch
- **Approve button** in Actions column for users with `special-costs.approve` (confirmation dialog)

### Special costs detail (`SpecialCosts/Show.vue`)

- Mirrors `Costs/Show.vue` structure with customer info, branch, technicians, concepts, catalog editor
- **Transfer notes banner** in orange, prominently displayed in the header
- **Approve button** for users with `special-costs.approve`
- **Version dropdown** to view historical versions
- Catalog editor enabled only when `canCreateCatalog` and catalog is not yet approved
- Editing items and clicking "Guardar nueva versión" creates a new `BudgetCatalog` (version N+1)

### Versioning behavior in special costs

- Creating a new version from special costs:
  - New `BudgetCatalog` created with `version = MAX(version) + 1`
  - New catalog inherits `needs_special_authorization = true` and `transfer_notes` from the previous one
  - Previous catalog's `needs_special_authorization` is cleared (`false`) — only the latest version appears in the list
  - Redirect to the new catalog's detail view automatically (not `back()`)

### Approval behavior in special costs

- Same as regular Costs approval:
  - `BudgetCatalog.approve()` called → `status = approved`
  - Ticket status → `Catálogo` (only if it was `Pendiente de aprobación`)
  - `CatalogApproved` notification dispatched to the seller
- Redirects to `special-costs.index` after approval (catalog disappears from list)
- Approved catalogs are immutable — no further editing

### Costs/Show.vue changes for special costs

- When `budget.latest_catalog.needs_special_authorization` is true:
  - "En revisión por Dirección" badge replaces Approve/Transfer buttons
  - Transfer button hidden

### Costs/Index.vue changes for special costs

- `CostService.getBudgetsForCosting()` includes `needs_special_authorization` in response
- When a row has `needs_special_authorization = true` and `catalog_status = pending_approval`:
  - "En revisión por Dirección" badge replaces Approve button
  - "Enviar a aprobación" button hidden
