# Construmax2 ERP — 12: Invoices Module

> **Purpose:** Invoice upload and tracking — attach invoice documents to budgets, track due dates, and manage overdue notifications.  
> **Context file covers:** Invoice listing, upload, overdue detection, status syncing.

---

## Key files

| Layer | File | Purpose |
|-------|------|---------|
| Controller | `InvoiceController.php` | List + upload |
| Action | `UploadInvoiceAction.php` | Upload + status cascade |
| Service | `InvoiceService.php` | Pending invoice queries |
| Notification | `InvoiceOverdue.php` | Overdue notification (mail + database) |
| Command | `CheckOverdueInvoices.php` | Cron: daily overdue detection |
| Vue pages | `Invoices/Index.vue` | Invoice listing with upload |
| Vue partials | `Invoices/Partials/TableList.vue` | Table + upload modal |
| Routes | `routes/web/invoices.php` | 2 routes |

---

## Routes

```
GET  /invoices                   invoices.index
POST /invoices/{budget}/upload   invoices.upload
```

---

## Data flow

### Upload flow
1. User navigates to `/invoices` → sees budgets whose tickets are in `Finalizado` or `Facturado` status
2. User clicks "Upload invoice" → modal captures `invoice_date`, `invoice_number`, and `file` (PDF/XML)
3. `UploadInvoiceAction::execute()`:
   - Updates budget: `invoice_date`, `invoice_number`, `status='Facturado'`
   - Cascades to ticket: if ticket is not already `Facturado`, updates to `Facturado`
   - Attaches file to `invoice_document` media collection on the budget

### Overdue detection (`CheckOverdueInvoices` command)
- Runs daily at 07:00 via Laravel scheduler
- Finds all tickets with `status='Facturado'` that have an `invoice_date` set
- For each, calculates due date: `invoice_date + customer.payment_days`
- If today IS the exact due date → dispatches `InvoiceOverdue` notification
- Note: only fires ON the due date, not before or after

---

## InvoiceService

`getPendingInvoices(array $filters)`:
- Returns paginated budgets (15/page) where related ticket status is `Finalizado` or `Facturado`
- Filters: `search` (ticket name or customer name), `status`
- Computes: `due_date` (invoice date + payment_days), invoice file URL
- Collects task evidence (media from all ticket tasks)

---

## Vue pages

### `Invoices/Index.vue`
- Table with columns: folio, customer, branch, ticket status, invoice status, amount, currency
- Upload modal: captures invoice date, number, and file
- Filters: search, status

---

## Dependencies on other modules

- **Budgets** (`07`): Invoice is attached to budget; status sync
- **Tickets** (`06`): Ticket status cascades to `Facturado` on invoice upload
- **Customers** (`05`): `payment_days` used for due date calculation
- **Notifications** (`13`): `InvoiceOverdue` notification dispatched by cron command
- **Notifications** (`13`): `ticketNeedsInvoice` notification dispatched when ticket reaches `Finalizado` status

---

## Known limitations / cautions

- **Only two statuses tracked:** The invoice module only considers `Finalizado` and `Facturado` ticket statuses — there's no `Pagado` invoice tracking
- **Overdue fires exactly once:** The cron only fires on the exact due date — if the cron misses a day (server down), the notification is skipped
- **No payment_days fallback:** If a customer has `payment_days = null`, the overdue calculation will fail — check for safeguards
- **Single file per invoice:** The upload replaces the previous `invoice_document` on the budget (singleFile collection) — no version history
- **No XML validation:** The file upload accepts any file type — there's no validation that it's actually an invoice PDF/XML
