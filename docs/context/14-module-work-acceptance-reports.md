# Construmax2 ERP — 14: Work Acceptance Reports Module

> **Business purpose:** Digital "Acta de recepción" (Work Acceptance Report) — a document that certifies completed work and is signed by the branch manager to authorize corresponding processes.
> **Context file covers:** Report generation, technician-editable fields, electronic signature (stored as PNG files on disk), PDF export, public signed-URL access, locking mechanism, internal editing, signature deletion, responsive mobile layout.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `WorkAcceptanceReportController.php` | Generate, show (internal & public), sign, update (internal), delete signature |
| Model | `WorkAcceptanceReport.php` | BelongsTo Ticket; scopes for signed/unsigned; `isSigned()`, `lock()`, `getSignatureUrlAttribute()` accessor, `$appends` |
| Model | `Ticket.php` | `hasOne(WorkAcceptanceReport)` relationship |
| Action | `CreateWorkAcceptanceReportAction.php` | Creates report with auto-populated dates/comments from tasks |
| Action | `SignWorkAcceptanceReportAction.php` | Saves signature as PNG file to disk, stores path in DB, locks report |
| Command | `MigrateSignaturesToDisk.php` | Migrates legacy base64 signatures from DB to storage files |
| FormRequest | `StoreWorkAcceptanceReportRequest.php` | Validates ticket_id on generation |
| FormRequest | `SignWorkAcceptanceReportRequest.php` | Validates signature_data + signatory_name |
| Vue pages | `WorkAcceptanceReports/Show.vue` | Print-friendly template with signature modal, edit modal, delete signature button |
| Vue components | `Signature/SignaturePad.vue` | Reusable canvas component (mouse + touch, responsive width) |
| Vue components | `Tickets/WorkAcceptanceReportCard.vue` | Card in ticket details "Tareas y seguimiento" tab |
| Routes | `routes/web/work-acceptance-reports.php` | 9 routes (4 public signed + 5 internal auth) |
| Routes | `routes/web.php` | `POST /tools/migrate-signatures` for web-based signature migration |

---

## Routes

### Public (signed URLs, no auth)
```
GET    work-acceptance-reports/public/{report}        public.show
POST   work-acceptance-reports/public/{report}/sign   public.store-signature
PUT    work-acceptance-reports/public/{report}         public.update
```

### Internal (auth)
```
POST   work-acceptance-reports                        store
GET    work-acceptance-reports/{report}               show
PUT    work-acceptance-reports/{report}               update
POST   work-acceptance-reports/{report}/sign          sign
DELETE work-acceptance-reports/{report}/signature     delete-signature
POST   work-acceptance-reports/{report}/generate-link generate-link
```

### Admin tool (auth, requires `manage roles-permissions`)
```
POST   tools/migrate-signatures                       tools.migrate-signatures
```

---

## Data model

### `work_acceptance_reports`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `ticket_id` | FK → tickets.id CASCADE (unique) | One report per ticket |
| `report_date` | date | Auto-set to current date on creation |
| `work_description` | text | nullable — must be filled manually by technician |
| `on_site_start` | datetime | nullable — technician-entered, converted to app timezone |
| `on_site_end` | datetime | nullable — technician-entered, converted to app timezone |
| `technician_comments` | text | nullable — technician-entered |
| `client_comments` | text | nullable — entered by branch manager at signing |
| `manager_name` | string | nullable — branch manager name |
| `signature_data` | longText | nullable — **Legacy.** Base64 PNG; kept for backward compatibility, nullified after `--clear-db` migration |
| `signature_path` | string | nullable — **New.** Relative path to PNG file in `storage/app/public/signatures/` (e.g. `signatures/3_1784591390.png`) |
| `signatory_name` | string | nullable — name typed at signing |
| `signed_at` | timestamp | nullable — auto-set when signed |
| `is_signed` | boolean | default `false` — locks document when true |
| `created_by` | FK → users.id | User who generated the report |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `belongsTo(Ticket)`, `belongsTo(User, created_by)`

### `signature_url` accessor

The model exposes a `signature_url` virtual attribute (via `$appends`):
- If `signature_path` is set → returns `asset('storage/' . $signature_path)`
- Otherwise → falls back to `signature_data` (legacy base64)
- This ensures both old and new signatures display seamlessly in the UI

---

## Workflow

### Generation
1. Internal user clicks "Generar acta de recepción" in ticket details → `WorkAcceptanceReportCard.vue`
2. `CreateWorkAcceptanceReportAction` creates the report with:
   - `report_date` = today
   - `work_description` = null (technician must fill manually)
   - `on_site_start` / `on_site_end` = auto-populated from task dates
   - `technician_comments` = auto-populated from task notes
3. Page refreshes; card updates to show "Ver acta de recepción" + "Copiar enlace público"

### Technician data entry
1. Technician accesses `PublicTask.vue` via signed URL
2. Editable "Acta de recepción" section with:
   - Work description (textarea)
   - On-site start/end datetime pickers
   - Technician comments (textarea)
3. Saves via `PUT public/{report}` (signed URL) → JSON response
4. Dates are converted from browser UTC to `America/Mexico_City` timezone before storing

### Signature flow
1. Branch manager opens the report via shared public link (or internal view)
2. Clicks "Clic para firmar" on the manager signature area → **responsive modal** opens:
   - 95% width on mobile (≤640px), 580px on desktop
   - SignaturePad canvas width is reactive (`Math.min(500, window.innerWidth - 80)`)
   - Touch and mouse input supported
3. Modal contains: full name, digital signature (canvas), optional comments
4. Submits via `POST /sign` → `SignWorkAcceptanceReportAction`:
   - Decodes base64 PNG, saves to `storage/app/public/signatures/{reportId}_{timestamp}.png`
   - Stores relative path in `signature_path` column
   - Locks report (`is_signed = true`, `signed_at = now()`)
5. Page refreshes showing signed state with watermark, signature image (via `signature_url`), and timestamp

### Internal editing (staff with `tickets.edit` permission)
1. "Editar acta" button (blue) opens a modal with:
   - **Info alert:** Explains that client, branch, service type, and technician data come from the ticket — edit the ticket to update those fields
   - Work description (textarea)
   - On-site start/end (datetime pickers)
   - Technician comments (textarea)
2. Submits via `PUT work-acceptance-reports/{report}` → `update()` method
3. Dates are converted from browser UTC to `America/Mexico_City` timezone before storing

### Delete signature (staff with `tickets.edit` permission)
1. "Eliminar firma" button (red, only visible when report is signed and user has `tickets.edit`)
2. Confirmation dialog via `ElMessageBox.confirm`
3. On confirm → `DELETE work-acceptance-reports/{report}/signature`:
   - Deletes the PNG file from disk (`Storage::disk('public')->delete()`)
   - Clears: `signature_path`, `signature_data`, `signatory_name`, `manager_name`, `client_comments`
   - Unlocks: sets `is_signed = false`, `signed_at = null`
4. Report is ready to be re-signed

### Locking mechanism
- **Before signature**: All fields editable by technician via `PublicTask.vue`; staff can edit via internal update route
- **After signature**: Document is read-only — `publicUpdate` returns 403, signature area shows "Firma no disponible"
- **After deleteSignature**: Document is unlocked and can be re-signed
- `lock()` method sets `is_signed = true` and `signed_at = now()`

---

## Vue components

### `WorkAcceptanceReports/Show.vue`
- Print-friendly layout matching `Costs/Print.vue` styling (orange/white/gray scheme)
- Displays all report data: client info, contractor info, project data, timestamps, technicians, comments
- Manager signature area: clickable when unsigned (opens responsive modal), shows signature image via `signature_url` when signed
- "FIRMADO" watermark overlay when signed
- **Edit modal** (staff only, gated by `can('tickets.edit')`): work description, timestamps, technician comments
- **Delete signature button** (staff only, when signed, gated by `can('tickets.edit')`): clears signature and unlocks report
- **Info alert** in edit modal: explains ticket-related data comes from the ticket itself
- PDF export via browser print with optimized `@media print` CSS (5mm margins, 10px fonts)
- `PdfInstructionsDialog` for print guidance
- Responsive signature modal: 95% width mobile, 580px desktop; canvas scales reactively

### `Components/Signature/SignaturePad.vue`
- Reusable canvas-based signature pad
- Supports mouse and touch input
- Exports as base64 PNG via `v-model`
- Props: `width`, `height`, `disabled`
- Placeholder text "Firma aqui", "Limpiar" button, "Firma capturada" indicator
- Handles window resize to maintain drawing quality
- Canvas uses `getBoundingClientRect()` scaling for accurate touch/pen tracking

### `Components/Tickets/WorkAcceptanceReportCard.vue`
- Embedded in the "Tareas y seguimiento" tab of ticket details
- States: no report → "Generar acta de recepción" button; report exists → "Ver acta" + "Copiar enlace público"
- "Copiar enlace público" generates a signed URL via AJAX and copies to clipboard with feedback
- Shows signed status with date+time

---

## Ticket list/kanban indicators

Both `TicketList.vue` and `TicketKanban.vue` show a visual indicator when a report exists:
- 🟢 Green `DocumentChecked` icon + tooltip "Acta de recepción firmada"
- 🟠 Amber `DocumentChecked` icon + tooltip "Acta de recepción pendiente de firma"

`workAcceptanceReport` is eager-loaded in the ticket index query.

---

## Dependencies on other modules

- **Tickets** (`06`): Core relationship — each report belongs to one ticket
- **Customers** (`05`): Customer data displayed on the report (name, branch, contact)
- **Technicians** (`09`): Technician names gathered from ticket assignments for the report
- **Users** (`03`): `created_by` tracks who generated the report

---

## Migrate legacy signatures to disk

Run the Artisan command to convert existing base64 signatures to PNG files:
```bash
php artisan signatures:migrate-to-disk           # Migrate only
php artisan signatures:migrate-to-disk --clear-db # Migrate and nullify signature_data
```

The command:
- Finds all reports with `signature_data` but no `signature_path`
- Decodes base64, saves PNG to `storage/app/public/signatures/`
- Updates `signature_path`, optionally clears `signature_data`
- Skips invalid base64 data gracefully
- Shows progress bar and migrated/failed counts

A web route `POST /tools/migrate-signatures` (requires `manage roles-permissions`) also triggers this.

**Important:** Run `php artisan storage:link` to create the `public/storage` → `storage/app/public` symlink so signature images are publicly accessible.

---

## Known limitations / cautions

- **Work description starts blank**: The technician must manually enter the work description via `PublicTask.vue` — it does not auto-populate from tasks
- **Timezone handling**: Browser sends dates in UTC; the backend converts to `America/Mexico_City` before storing. Always use the `datetime` picker components; raw string input may cause timezone issues
- **Signature storage**: Signatures are now stored as PNG files in `storage/app/public/signatures/` (~5-10 KB each). The old `signature_data` column is kept for backward compatibility and can be nullified via `--clear-db` after migration
- **Public routes use signed URLs**: The public show and sign endpoints require valid Laravel signed URLs. These expire based on configuration
- **One report per ticket**: The `ticket_id` column has a unique constraint — only one work acceptance report can exist per ticket
- **Edit/delete gated by `tickets.edit` permission**: Only staff with this permission see the edit and delete signature buttons
- **Storage route in `web.php`**: The custom `/storage/{extra}` fallback route now returns proper `Content-Type` headers via `File::mimeType()` for correct image serving
