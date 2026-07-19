# Construmax2 ERP — 14: Work Acceptance Reports Module

> **Business purpose:** Digital "Acta de recepción" (Work Acceptance Report) — a document that certifies completed work and is signed by the branch manager to authorize corresponding processes.
> **Context file covers:** Report generation, technician-editable fields, electronic signature, PDF export, public signed-URL access, locking mechanism.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `WorkAcceptanceReportController.php` | Generate, show (internal & public), sign, update |
| Model | `WorkAcceptanceReport.php` | BelongsTo Ticket; scopes for signed/unsigned; `isSigned()`, `lock()` |
| Model | `Ticket.php` | `hasOne(WorkAcceptanceReport)` relationship |
| Action | `CreateWorkAcceptanceReportAction.php` | Creates report with auto-populated dates/comments from tasks |
| Action | `SignWorkAcceptanceReportAction.php` | Saves signature data and locks the report |
| FormRequest | `StoreWorkAcceptanceReportRequest.php` | Validates ticket_id on generation |
| FormRequest | `SignWorkAcceptanceReportRequest.php` | Validates signature_data + signatory_name |
| Vue pages | `WorkAcceptanceReports/Show.vue` | Print-friendly template with inline signature modal |
| Vue components | `Signature/SignaturePad.vue` | Reusable canvas component (mouse + touch) |
| Vue components | `Tickets/WorkAcceptanceReportCard.vue` | Card in ticket details "Tareas y seguimiento" tab |
| Routes | `routes/web/work-acceptance-reports.php` | 7 routes (4 public signed + 3 internal auth) |

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
POST   work-acceptance-reports/{report}/sign          sign
POST   work-acceptance-reports/{report}/generate-link generate-link
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
| `signature_data` | longText | nullable — base64 PNG from signature canvas |
| `signatory_name` | string | nullable — name typed at signing |
| `signed_at` | timestamp | nullable — auto-set when signed |
| `is_signed` | boolean | default `false` — locks document when true |
| `created_by` | FK → users.id | User who generated the report |
| `created_at` / `updated_at` | timestamps | |

**Relationships:** `belongsTo(Ticket)`, `belongsTo(User, created_by)`

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
2. Clicks "Clic para firmar" on the manager signature area → modal opens
3. Modal contains: full name, digital signature (canvas), optional comments
4. Submits via `POST /sign` → report is locked (`is_signed = true`, `signed_at = now()`)
5. Page refreshes showing signed state with watermark, signature image, and timestamp

### Locking mechanism
- **Before signature**: All fields editable by technician via `PublicTask.vue`
- **After signature**: Document is read-only — `publicUpdate` returns 403, signature area shows "Firma no disponible"
- `lock()` method sets `is_signed = true` and `signed_at = now()`

---

## Vue components

### `WorkAcceptanceReports/Show.vue`
- Print-friendly layout matching `Costs/Print.vue` styling (orange/white/gray scheme)
- Displays all report data: client info, contractor info, project data, timestamps, technicians, comments
- Manager signature area: clickable when unsigned (opens modal), shows signature image when signed
- "FIRMADO" watermark overlay when signed
- PDF export via browser print with optimized `@media print` CSS (5mm margins, 10px fonts)
- `PdfInstructionsDialog` for print guidance

### `Components/Signature/SignaturePad.vue`
- Reusable canvas-based signature pad
- Supports mouse and touch input
- Exports as base64 PNG via `v-model`
- Props: `width`, `height`, `disabled`
- Placeholder text "Sign here", clear button, signature captured indicator

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

## Known limitations / cautions

- **Work description starts blank**: The technician must manually enter the work description via `PublicTask.vue` — it does not auto-populate from tasks
- **Timezone handling**: Browser sends dates in UTC; the backend converts to `America/Mexico_City` before storing. Always use the `datetime` picker components; raw string input may cause timezone issues
- **Signature data can be large**: `signature_data` is stored as `longText` (base64 PNG). The canvas default is 600×200px which produces ~8-15KB of base64 data
- **Public routes use signed URLs**: The public show and sign endpoints require valid Laravel signed URLs. These expire based on configuration
- **One report per ticket**: The `ticket_id` column has a unique constraint — only one work acceptance report can exist per ticket
