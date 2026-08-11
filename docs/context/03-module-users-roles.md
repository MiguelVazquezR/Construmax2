# Construmax2 ERP — 03: Users & Roles/Permissions

> **Business purpose:** User management (employees), role-based access control (RBAC), and permission management.  
> **Context file covers:** Users CRUD, Roles CRUD, Permissions CRUD (developer-gated).

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `UserController.php` | Full CRUD + toggleStatus |
| Controller | `RolePermissionController.php` | Roles CRUD + Permissions CRUD |
| Model | `User.php` | HasRoles trait, HasApiTokens, filter scope |
| Model | `Employee.php` | BelongsTo User |
| Config | `config/permission.php` | Spatie config: teams disabled, 24h cache |
| Vue pages | `Users/` | Index, Create, Edit, Show |
| Vue pages | `RolePermissions/Index.vue` | Single-page RBAC management |
| Composables | `usePermissions.js` | `can()` helper for Vue |
| Routes | `routes/web/users.php` | 8 routes |
| Routes | `routes/web/roles-permissions.php` | 7 routes (prefix: `config/`) |

---

## User management

### Routes
```
GET    /users                    users.index
GET    /users/create             users.create
POST   /users                    users.store
GET    /users/{user}             users.show
GET    /users/{user}/edit        users.edit
PUT    /users/{user}             users.update
DELETE /users/{user}             users.destroy
PUT    /users/{user}/toggle-status  users.toggle-status
POST   /users/bulk-delete          users.bulk-destroy
```

### Controller behavior (`UserController`)

| Method | What it does |
|--------|-------------|
| `index` | Lists users (excludes super-admin #1 and users with a `Technician` record), paginated, searchable by name/email, loads employee + roles |
| `store` | Creates User + Employee + syncs Spatie roles in a DB transaction |
| `update` | Updates User name/email/password (optional), upserts Employee, syncs roles |
| `destroy` | Nullifies/cleans foreign keys in `ticket_tasks` (set null), deletes `calendars`, `calendar_participants`, `technician_payments`, `field_work_schedules` rows, reassigns `budgets.user_id` to admin (#1), then deletes user |
| `bulkDestroy` | Same FK cleanup as `destroy` but for multiple IDs at once via `POST /users/bulk-delete` with `{ ids: [...] }` payload. Excludes super-admin (#1) |
| `toggleStatus` | Toggles `users.is_active` — soft enable/disable |

### User creation form fields
`name`, `email`, `password`, `roles[]` (multi-select), `department` (select), `position`, `phone`

### User show page
- Hero card: avatar, name, email, roles, department
- Tabs: General (employee info), Tickets (assigned as seller)

---

## Roles & Permissions management

### Routes (prefix: `config/`, name prefix: `config.`)
```
GET    /config/roles-permissions       config.roles-permissions.index
POST   /config/roles                   config.roles.store
PUT    /config/roles/{role}            config.roles.update
DELETE /config/roles/{role}            config.roles.destroy
POST   /config/permissions             config.permissions.store
PUT    /config/permissions/{permission}  config.permissions.update
DELETE /config/permissions/{permission}  config.permissions.destroy
```

### Controller behavior (`RolePermissionController`)

| Method | What it does |
|--------|-------------|
| `index` | Renders page with all roles, all permissions, permissions grouped by category |
| `storeRole` | Creates role with optional permission assignments |
| `updateRole` | Updates name, syncs permissions |
| `destroyRole` | Deletes role (blocked if any user has this role) |
| `storePermission` | **Developer-only** (user #1) — creates permission with name, category, description |
| `updatePermission` | **Developer-only** — updates permission |
| `destroyPermission` | **Developer-only** — deletes permission |

### Permission naming convention
All permissions use kebab-case: `create service-orders`, `edit invoices`, `delete customers`

### How authorization works
1. Permissions assigned to roles, roles assigned to users
2. Controllers check via `FormRequest::authorize()` → `$this->user()->can('permission-name')`
3. Frontend checks via `usePermissions` composable: `can('permission-name')` — uses `$page.props.can` object passed from backend
4. Never check roles directly — always check permissions

---

## Vue pages

### `Users/Index.vue`
- Checkbox column for bulk selection → **"Eliminar (N)"** button appears when rows are selected
- Table with columns: name, email, roles, department, status (active/inactive)
- Row click → detail page
- Toggle active/inactive status with confirmation
- Only shows users **without** a `Technician` record (technicians are managed separately)

### `Users/Create.vue` / `Users/Edit.vue`
- Form: name, email, password, roles (multi-select), department, position, phone
- Element Plus validation

### `Users/Show.vue`
- Profile view with tabs: General, Tickets

### `RolePermissions/Index.vue`
- Single page with two sections:
  1. **Roles tab:** create/edit/delete roles, assign permissions via checkboxes per category
  2. **Permissions tab:** list all permissions grouped by category (developer-only CRUD actions)

---

## Dependencies on other modules

- **Auth** (`02`): `User` model is shared; Jetstream profile photos used for avatars
- **Tickets** (`06`): Users appear as sellers (`tickets.seller_id`)
- **Technicians** (`09`): Each technician has a User record
- **Calendar** (`10`): Users participate in calendar events
- **Deposits** (`11`): `created_by` and `approved_by` reference users
- **Notifications** (`13`): `NotificationSetting` is per-user

---

## Known limitations / cautions

- **Super-admin is hardcoded:** User ID #1 is treated as super-admin — excluded from listings, granted all privileges
- **Developer-only gating:** Permission CRUD is gated to user ID #1 in the controller, not via Spatie permissions — if user #1 is deleted, no one can manage permissions
- **Teams disabled:** All roles and permissions are global — no per-team scoping
- **No role hierarchy:** Spatie roles are flat — no inheritance
- **No soft deletes on users:** `destroy()` permanently deletes — FK cleanup handles related records: nullifies nullable FKs, deletes owned rows (calendars, participants, tech payments, field schedules), reassigns budgets to admin (#1). Cascade handles `employees` and `technicians`.
