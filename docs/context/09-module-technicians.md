# Construmax2 ERP — 09: Technicians Module

> **Purpose:** External/internal technician profiles — specialties, geolocation, bank accounts, ratings, status management, and performance tracking.  
> **Context file covers:** Technicians CRUD, bank accounts, documents, payments history, quick-create.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `TechnicianController.php` | Full CRUD + bank accounts, rating, status, quick-create |
| Model | `Technician.php` | Implements HasMedia; BelongsTo User; scopes |
| Model | `TechnicianBankAccount.php` | Implements HasMedia (QR); BelongsTo Technician |
| Vue pages | `Technicians/` | Index (card grid), Create, Edit, Show |
| Vue partials | `Technicians/Partials/` | 6 sub-components |
| Routes | `routes/web/technicians.php` | 15 routes |

---

## Routes

```
GET    /technicians                                    technicians.index
GET    /technicians/create                             technicians.create
POST   /technicians                                    technicians.store
POST   /technicians/quick                              technicians.quick-store
GET    /technicians/{technician}                       technicians.show
GET    /technicians/{technician}/edit                  technicians.edit
PUT    /technicians/{technician}                       technicians.update
DELETE /technicians/{technician}                       technicians.destroy
PUT    /technicians/{technician}/rating                technicians.update-rating
PUT    /technicians/{technician}/status                technicians.update-status
DELETE /technicians/{technician}/media/{media}         technicians.delete-media
POST   /technicians/{technician}/bank-accounts         technicians.bank-accounts.store
POST   /technicians/{technician}/bank-accounts/{account}  technicians.bank-accounts.update
DELETE /technicians/{technician}/bank-accounts/{account}  technicians.bank-accounts.destroy
PUT    /technicians/{technician}/bank-accounts/{account}/favorite  technicians.bank-accounts.favorite
```

---

## Data model

### Technician
- `user_id`: FK to users (each technician has a User record for auth/login)
- `is_internal`: boolean — internal employees vs external contractors
- `level`: `Encargado` (lead) or `Auxiliar/Ayudante` (assistant)
- `specialties`: JSON array — 20 predefined options (electric, plumbing, HVAC, drywall, painting, waterproofing, masonry, welding, glass/aluminum, networking, locksmith, cleaning, carpentry, tile/flooring, gardening, fumigation, CCTV, domotics, elevator maintenance)
- `status`: Activo, Inactivo, En revisión, Vetado
- `rating_avg`: decimal(3,2), updated via rating action
- Bank fields (legacy): `bank_name`, `bank_account`, `clabe` — prefer using `TechnicianBankAccount` relationship
- Location: `state`, `city`, `colony`, `zip_code`, `coverage_radius_km`

### TechnicianBankAccount
- Multiple accounts per technician
- `bank_name`, `account_number`, `card_number`, `clabe`, `branch_number`
- `is_favorite`: boolean, exactly one favorite per technician
- Has media: `bank_qr` (single file for QR code)

---

## Controller behavior

| Method | What it does |
|--------|-------------|
| `index` | Card grid with search, specialty, state, is_internal filters; returns JSON for AJAX or Inertia page |
| `store` | Creates User + Technician in a transaction; photo optimization; optional tax file upload |
| `update` | Updates User + Technician; handles photo and tax file replacement |
| `quickStore` | Quick-create from ticket form: name + phone only, returns JSON |
| `show` | Profile with ticket history, payments, KPIs (total tickets, completion rate, earnings) |
| `updateStatus` | Changes status (Activo, Inactivo, En revisión, Vetado) |
| `updateRating` | Updates `rating_avg` |
| `storeBankAccount` | Adds bank account (first one auto-favorited) |
| `setFavoriteBankAccount` | Sets one account as favorite, unfavoriting others |

---

## Vue pages

### `Technicians/Index.vue`
- Card-based grid with avatar, rating (color-coded: red ≤3, orange ≤4.5, green >4.5), specialties, status, ticket count
- Toggle status button
- Filters: search, state, specialty

### `TechnicianForm.vue` (core)
- User basics: name, email, phone, photo
- Geolocation: state, city, colony, zip, coverage radius
- Specialties: multi-select with 20 presets + custom
- Level: Encargado or Auxiliar/Ayudante
- Fiscal: legal name, RFC
- Banking: bank, account, CLABE (legacy fields)
- Internal notes
- Bank accounts management (inline `BankAccountsTab`)

### `Technicians/Show.vue`
- Tabs: Profile, History (tickets), Documents, Payments, Bank Accounts
- Rating update popover
- Status change
- Google Maps link, WhatsApp link
- KPI cards

---

## Involvement tracking
Since technicians are stored as JSON arrays on tickets (not FKs), the `Ticket::scopeWhereInvolved($userId)` scope searches for the technician's user ID in both `technicians` and `assistant_technicians` JSON columns, plus checks if they have any assigned `TicketTask` records.

The `Technician::involvedTickets()` method delegates to this scope.

---

## Dependencies on other modules

- **Users** (`03`): Each technician has a User record
- **Tickets** (`06`): Technicians assigned via JSON arrays; involved tickets queried
- **Budgets** (`07`): `TechnicianPayment` links technician payments to budgets
- **Deposits** (`11`): Deposits reference technicians and their bank accounts

---

## Known limitations / cautions

- **Technician-to-ticket is JSON, not FK:** No referential integrity between tickets and technicians — deleting a technician leaves stale IDs in ticket JSON arrays
- **`involvedTickets` is expensive:** JSON column searching (`WHERE JSON_CONTAINS...`) does not use indexes well; performance degrades with large ticket volumes
- **Legacy banking fields:** `bank_name`, `bank_account`, `clabe` on the `technicians` table are legacy; the `TechnicianBankAccount` model is the preferred approach, but both may contain data
- **Rating is manual:** `rating_avg` is only updated via the `updateRating` endpoint — no automated KPI-based rating calculation yet (method exists as placeholder)
- **Specialty filtering supports Unicode-escaped JSON:** The filter scope has a fallback for `LIKE` matching — this may produce false positives on partial matches
