# Construmax2 ERP — 08: Costs Module

> **Business purpose:** Versioned cost catalogs for budgets — manage materials and labor line items, calculate totals with IVA, Empeño Fácil special calculations, and print-friendly budget layouts.  
> **Context file covers:** Cost catalog CRUD, materials/labor tables, print views, Empeño Fácil mode.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `CostController.php` | List, show, catalog CRUD, print views |
| Service | `CostService.php` | Budget listing for costing, catalog details |
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
| Routes | `routes/web/costs.php` | 5 routes |

---

## Routes

```
GET  /costs                              costs.index
GET  /costs/{budget}                     costs.show
GET  /costs/{budget}/print               costs.print
GET  /costs/{budget}/print-empeno-facil  costs.print-empeno-facil
POST /costs/{budget}/catalog             costs.store-catalog
```

---

## Data model

### BudgetCatalog
- `budget_id`: FK to budgets
- `version`: integer, auto-incremented per budget
- Financials: `subtotal`, `iva`, `total`
- Empeño Fácil fields: `non_installation_labor` (12% overhead), `labor_utility` (18% utility)

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

### Empeño Fácil mode
- Activated when the customer ID is 2 (hardcoded)
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
- Filters: search, catalog status (with/without/all), branch
- Each row shows: ticket folio, customer, branch, catalog version, total

### `Costs/Show.vue` (core editor)
- Two editable tables: `MaterialsTable` + `LaborTable`
- Inline editing: description, unit, quantity, unit price → auto-calculates total
- Add/delete rows
- IVA toggle
- Empeño Fácil mode: shows `EmpenoFacilTotals` card with auto-calculations
- Report number editing (updates ticket's `report_number`)
- Save button creates a new catalog version

### `Costs/Print.vue`
- Print-friendly layout
- Corporate header (logo, branch, city, folio)
- Concepts table with totals
- Bank info section
- PDF instructions dialog

### `Costs/PrintEmpenoFacil.vue`
- Empeño Fácil specific print layout
- Separates materials and labor sections with subtotals
- Custom calculations displayed
- Bank details with copy-to-clipboard

---

## Dependencies on other modules

- **Budgets** (`07`): Catalogs belong to budgets; budget's `total_cost` falls back to catalog total
- **Tickets** (`06`): Ticket folio, customer, branch info displayed; report number editing updates ticket
- **Customers** (`05`): Customer ID #2 is the Empeño Fácil customer

---

## Known limitations / cautions

- **Empeño Fácil is hardcoded to customer ID 2:** If the customer ID changes or different customers need this format, the logic needs refactoring
- **No item-level permissions:** All catalog editing is gated by a single `canCreateCatalog` prop
- **IVA calculation is client-side only:** The 16% IVA is hardcoded in JS — if the rate changes, multiple files need updates
- **Catalog versioning is append-only:** There's no way to delete a catalog version or revert to a previous one from the UI
- **Report number lives on the ticket:** Editing it from the costs page is a convenience feature — be aware of potential race conditions if edited from multiple places
