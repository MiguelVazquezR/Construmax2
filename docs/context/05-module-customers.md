# Construmax2 ERP — 05: Customers Module

> **Business purpose:** CRM entity management — customers and prospects with branches, contacts, file attachments, and logo management.  
> **Context file covers:** Customers CRUD, branches, contacts, prospect conversion.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `CustomerController.php` | Full CRUD + custom actions |
| Model | `Customer.php` | Implements HasMedia (logo, files) |
| Model | `CustomerBranch.php` | BelongsTo Customer, BelongsToMany Contact |
| Model | `CustomerContact.php` | BelongsTo Customer, BelongsToMany Branch |
| Vue pages | `Customers/` | Index (card grid), Create, Edit, Show |
| Vue partial | `Customers/Partials/CustomerForm.vue` | Core form shared by Create + Edit |
| Vue partial | `Tickets/Partials/QuickBranchModal.vue` | Quick-create branch from ticket form |
| Routes | `routes/web/customers.php` | 13 routes |

---

## Routes

```
GET     /customers                              customers.index
GET     /customers/create                       customers.create
POST    /customers                              customers.store
GET     /customers/{customer}                   customers.show
GET     /customers/{customer}/edit              customers.edit
PUT     /customers/{customer}                   customers.update
DELETE  /customers/{customer}                   customers.destroy
PUT     /customers/{customer}/toggle-status     customers.toggle-status
POST    /customers/{customer}/upload-files      customers.upload-files
DELETE  /customers/{customer}/media/{media}     customers.media.destroy
DELETE  /customers/{customer}/logo              customers.logo.destroy
POST    /customers/quick-branch                 customers.quick-branch
PUT     /customers/{customer}/convert-to-customer  customers.convert-to-customer
```

---

## Data model

### Customer
- `type`: `customer` or `prospect`
- Core fields: `name`, `business_name`, `rfc` (Mexican tax ID)
- Payment fields: `payment_condition`, `payment_method`, `invoice_usage`, `currency`, `payment_days` (credit days)
- `is_active`: soft enable/disable

### Branch (CustomerBranch)
- Location: `country`, `region` (state), `city`, `unit` (building/suite), `branch_name`
- Each customer can have multiple branches
- Branches can have multiple contacts via `customer_branch_contact` pivot

### Contact (CustomerContact)
- `name`, `email`, `phone`, `position`
- Each customer can have multiple contacts
- Contacts can be associated with multiple branches

---

## Controller behavior

| Method | What it does |
|--------|-------------|
| `index` | Card-based grid, filterable by search/region/contact, paginated. Shows logo, contact info, ticket count |
| `store` | Creates customer (or prospect) + branches + contacts (with branch associations) + logo + files — all in a DB transaction |
| `update` | Upserts branches/contacts via index matching, deletes removed relations |
| `toggleStatus` | Toggles `is_active` |
| `convertToCustomer` | Changes `type` from `prospect` to `customer` |
| `quickStoreBranch` | Quick-create branch via API (used from ticket form — returns JSON) |
| `uploadFiles` / `deleteMedia` / `deleteLogo` | Spatie Media Library management |

---

## Vue pages

### `Customers/Index.vue`
- Card-based grid (not table)
- Each card shows: logo, name, business name, contact info, ticket count
- Status toggle button (activate/deactivate)
- Filters: search, region, contact

### `CustomerForm.vue` (shared by Create + Edit)
- Type selector: customer vs prospect
- Business name, RFC, payment conditions, currency
- Branches: dynamic add/remove rows (country, region, city, unit, name)
- Contacts: dynamic add/remove rows (name, email, phone, position) with branch association checkboxes
- Logo upload
- File upload

### `Customers/Show.vue`
- Tabs:
  1. **General:** Branches + Contacts lists with WhatsApp links
  2. **Tickets:** Associated tickets table
  3. **Files:** Uploaded documents

---

## Quick-create from ticket form
- `QuickBranchModal.vue`: Opens from ticket creation — creates a branch for a selected customer via `customers.quick-branch` route, returns JSON
- Customer select in ticket form triggers cascading load of branches and contacts

---

## Dependencies on other modules

- **Tickets** (`06`): Customers are the primary FK on tickets; branches and contacts cascade into ticket creation
- **Budgets** (`07`): HasOneThrough Customer via Ticket
- **Media** (Spatie): Logo and file uploads use Spatie Media Library

---

## Known limitations / cautions

- **No address normalization:** Country/region/city are free-text strings, not normalized lookups — may cause filtering inconsistencies
- **Branch-contact pivot editing:** The `update` method uses index-based matching to upsert — if the client sends mismatched indices, data could be misaligned
- **Soft deletes:** `destroy()` appears to be a hard delete — check the actual implementation; FK cascades may propagate
- **Prospect conversion:** `convertToCustomer` simply changes the `type` field — no additional business logic or validation
