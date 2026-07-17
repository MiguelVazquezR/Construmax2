<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\TaskTemplate;
use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TicketController extends Controller
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 20);
        $sort = $request->input('sort', 'created_at'); 

        $query = Ticket::with(['customer', 'contact', 'branch', 'tasks.assignee', 'budget.latestCatalog', 'seller', 'workAcceptanceReport']);

        // FILTRO POR ASESOR: si no tiene permiso de ver todos, solo muestra sus tickets
        if (!$request->user()->can('tickets.index-all')) {
            $query->where('seller_id', $request->user()->id);
        }

        // BÚSQUEDA POR FOLIO
        if ($request->filled('folio')) {
            $folio = $request->input('folio');
            preg_match('/\d+/', $folio, $matches);
            if (!empty($matches[0])) {
                $query->where('id', $matches[0]);
            }
        }

        // FILTROS AVANZADOS
        if ($request->filled('customer')) {
            $query->where('customer_id', $request->input('customer'));
        }

        if ($request->filled('region')) {
            $like = '%' . $request->input('region') . '%';

            // utf8mb4_unicode_ci es insensible a acentos y mayúsculas, así que un solo LIKE basta
            $query->whereHas('branch', function($q) use ($like) {
                $q->whereRaw('region COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                  ->orWhereRaw('branch_name COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                  ->orWhereRaw('unit COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                  ->orWhereRaw('country COLLATE utf8mb4_unicode_ci LIKE ?', [$like]);
            });
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('technician')) {
            $techId = $request->input('technician');
            $query->where(function($q) use ($techId) {
                $q->whereJsonContains('technicians', (string)$techId)
                  ->orWhereJsonContains('technicians', (int)$techId)
                  ->orWhereHas('tasks', function($t) use ($techId) {
                      $t->where('user_id', $techId);
                  });
            });
        }

        if ($request->filled('seller')) {
            $query->where('seller_id', $request->input('seller'));
        }

        // FILTRO POR CATÁLOGO
        if ($request->filled('has_catalog')) {
            $catalogFilter = $request->input('has_catalog');
            if ($catalogFilter === 'yes') {
                $query->whereHas('budget.latestCatalog');
            } elseif ($catalogFilter === 'no') {
                $query->where(function ($q) {
                    $q->doesntHave('budget')
                      ->orWhereHas('budget', function ($b) {
                          $b->doesntHave('latestCatalog');
                      });
                });
            }
        }

        // Default active statuses (exclude finalized/completed)
        $defaultStatuses = ['Borrador', 'Programado', 'Levantamiento', 'Catálogo', 'Proceso de ejecución', 'Ejecutado', 'Finalizado'];

        if ($request->has('status')) {
            $statusFilter = $request->input('status', []);
            if (is_array($statusFilter) && !empty($statusFilter)) {
                if (in_array('all', $statusFilter)) {
                    // Show all — no status filter
                } else {
                    $query->whereIn('status', $statusFilter);
                }
            } else {
                // Empty or invalid: use default active statuses
                $query->whereIn('status', $defaultStatuses);
            }
        } else {
            // Default: show only active statuses
            $query->whereIn('status', $defaultStatuses);
        }

        // ORDENAMIENTO
        if ($sort === 'start_date') {
            $query->orderBy('scheduled_start', 'desc');
        } elseif ($sort === 'delay') {
            $query->orderByRaw("CASE WHEN status IN ('Ejecutado', 'Facturado', 'Pagado', 'Cancelado') THEN 2 ELSE 1 END")
                  ->orderBy('scheduled_end', 'asc');
        } elseif ($request->filled('has_catalog') && $request->input('has_catalog') === 'yes') {
            // When filtering by catalog, sort by latest catalog creation date (newest first)
            $query->orderBy(
                \App\Models\BudgetCatalog::select('budget_catalogs.created_at')
                    ->join('budgets', 'budgets.id', '=', 'budget_catalogs.budget_id')
                    ->whereColumn('budgets.ticket_id', 'tickets.id')
                    ->orderBy('budget_catalogs.version', 'desc')
                    ->limit(1),
                'desc'
            );
        } else {
            // Default: created_at
            $query->orderBy('created_at', 'desc');
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $query->paginate($perPage)->withQueryString(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'technicians' => User::whereHas('technician')->with('technician')->orderBy('name')->get(['id', 'name']),
            'sellers' => User::whereHas('ticketsAsSeller')->orderBy('name')->get(['id', 'name']),
            'canViewAll' => $request->user()->can('tickets.index-all'),
            'filters' => [
                'folio' => $request->input('folio'),
                'customer' => $request->input('customer'),
                'region' => $request->input('region'),
                'priority' => $request->input('priority'),
                'technician' => $request->input('technician'),
                'seller' => $request->input('seller'),
                'status' => $request->input('status', $defaultStatuses),
                'has_catalog' => $request->input('has_catalog'),
                'perPage' => $perPage,
                'sort' => $sort,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Tickets/Create', [
            'users' => User::where('id', '!=', 1)->with(['employee', 'technician'])->get(),
            'customers' => Customer::where('is_active', true)->with(['contacts', 'branches'])->get(),
            'templates' => TaskTemplate::where('is_active', true)->with('items')->get(),
            'serviceTypes' => ServiceType::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_contact_id' => 'required|exists:customer_contacts,id',
            'customer_branch_id' => 'nullable|exists:customer_branches,id',
            'seller_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'service_type' => 'required|string|max:255',
            'report_number' => 'nullable|string|max:255',
            'duration' => 'nullable|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
            'assistant_technicians' => 'nullable|array',
            'assistant_technicians.*' => 'exists:users,id',
            'priority' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
            'task_template_id' => 'nullable|exists:task_templates,id',
            'uploaded_files' => 'nullable|array',
            'uploaded_files.*' => 'file|max:10240',
        ]);

        $ticket = Ticket::create($validated);

        // Generate tasks from template for lead technicians, or auxiliaries if no leads
        $templateId = $validated['task_template_id'] ?? null;
        $leadTechs = $validated['technicians'] ?? [];
        $auxTechs = $validated['assistant_technicians'] ?? [];

        if (!empty($templateId)) {
            if (!empty($leadTechs)) {
                $ticket->generateTasksFromTemplate($templateId, $leadTechs);
            } elseif (!empty($auxTechs)) {
                // No lead technicians — assign template tasks to the first auxiliary
                $ticket->generateTasksFromTemplate($templateId, [$auxTechs[0]]);
            }
        }

        // Handle file uploads
        if ($request->hasFile('uploaded_files')) {
            foreach ($request->file('uploaded_files') as $file) {
                $ticket->addMedia($file)
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('ticket_evidence');
            }
        }

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket operativo generado correctamente.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'customer', 
            'contact', 
            'branch',
            'seller',
            'budget.concepts',
            'budget.payments',
            'budget.latestCatalog',
            'budget.responsible',
            'budget.technicianPayments.technician.technician',
            'budget.technicianPayments.media',
            'tasks.assignee', 
            'tasks.media', 
            'media',
            'workAcceptanceReport',
        ]);
        
        $ticket->append('progress', 'folio'); 

        $ticket->tasks->transform(function ($task) use ($ticket) {
            if ($task->user_id) {
                $task->share_url = URL::signedRoute('tickets.public.job-order', [
                    'ticket' => $ticket->id,
                    'user' => $task->user_id
                ]);
            }
            return $task;
        });

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'users' => User::where('id', '!=', 1)->with(['employee', 'technician'])->get(),
        ]);
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $request->validate(['status' => 'required|string']);
        $ticket->update(['status' => $request->status]);
        return back()->with('success', 'Estatus actualizado.');
    }

    public function updateReportNumber(Request $request, Ticket $ticket)
    {
        $request->validate(['report_number' => 'nullable|string|max:255']);
        $ticket->update(['report_number' => $request->report_number]);
        return back()->with('success', 'Número de reporte actualizado.');
    }

    public function updateField(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'field' => 'required|string|in:report_number,scheduled_start,scheduled_end',
            'value' => 'nullable|string|max:255',
        ]);

        $ticket->update([$validated['field'] => $validated['value']]);
        return back()->with('success', 'Campo actualizado.');
    }

    public function updateTechnicians(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
            'assistant_technicians' => 'nullable|array',
            'assistant_technicians.*' => 'exists:users,id',
        ]);

        $oldTechnicians = $ticket->technicians ?? [];
        $ticket->update($validated);
        $newTechnicians = $ticket->technicians ?? [];

        $this->reassignTechnicianData($ticket, $oldTechnicians, $newTechnicians);

        return back()->with('success', 'Técnicos actualizados correctamente.');
    }

    public function edit(Ticket $ticket)
    {
        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket->load(['customer', 'contact', 'branch', 'seller']),
            'users' => User::where('id', '!=', 1)->with(['employee', 'technician'])->get(),
            'customers' => Customer::where('is_active', true)->with(['contacts', 'branches'])->get(),
            'templates' => TaskTemplate::where('is_active', true)->with('items')->get(),
            'serviceTypes' => ServiceType::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_contact_id' => 'required|exists:customer_contacts,id',
            'customer_branch_id' => 'nullable|exists:customer_branches,id',
            'seller_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'service_type' => 'required|string|max:255',
            'report_number' => 'nullable|string|max:255',
            'duration' => 'nullable|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
            'assistant_technicians' => 'nullable|array',
            'assistant_technicians.*' => 'exists:users,id',
            'priority' => 'required|string',
            'status' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
            'task_template_id' => 'nullable|exists:task_templates,id',
        ]);

        $oldTechnicians = $ticket->technicians ?? [];
        $ticket->update($validated);
        $newTechnicians = $ticket->technicians ?? [];

        // When a technician is replaced, reassign all tasks and payments
        // from the removed technician to the new one
        $this->reassignTechnicianData($ticket, $oldTechnicians, $newTechnicians);

        // Generate tasks from template if selected and ticket has no tasks yet
        if ($request->filled('task_template_id') && !empty($newTechnicians)) {
            $hasTasks = $ticket->tasks()->exists();
            if (!$hasTasks) {
                $ticket->generateTasksFromTemplate($request->input('task_template_id'), $newTechnicians);
                $ticket->updateStatusBasedOnTasks();
            }
        }

        return redirect()->route('tickets.show', $ticket->id)->with('success', 'Ticket actualizado.');
    }

    /**
     * Reassign tasks and payments from removed technicians to replacement technicians.
     */
    private function reassignTechnicianData(Ticket $ticket, array $oldIds, array $newIds): void
    {
        // Normalize to integers
        $oldIds = array_map('intval', $oldIds);
        $newIds = array_map('intval', $newIds);

        // Also include assistant_technicians
        $oldAssistants = array_map('intval', $ticket->assistant_technicians ?? []);
        $allOldIds = array_merge($oldIds, $oldAssistants);
        $allNewIds = array_merge($newIds, array_map('intval', $ticket->assistant_technicians ?? []));

        $removedIds = array_diff($allOldIds, $allNewIds);
        $addedIds = array_diff($allNewIds, $allOldIds);

        // Only proceed if there's a replacement (removed + added match count)
        if (empty($removedIds) || empty($addedIds)) {
            return;
        }

        // Pair each removed technician with a new one in order
        $removedIds = array_values($removedIds);
        $addedIds = array_values($addedIds);

        foreach ($removedIds as $i => $oldId) {
            $newId = $addedIds[$i] ?? $addedIds[0];

            // Reassign ticket tasks
            $ticket->tasks()->where('user_id', $oldId)->update(['user_id' => $newId]);

            // Reassign technician payments via the ticket's budget
            if ($ticket->budget) {
                $ticket->budget->technicianPayments()
                    ->where('user_id', $oldId)
                    ->update(['user_id' => $newId]);
            }
        }
    }

    public function updateImportantNote(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'important_note' => 'nullable|string|max:500',
        ]);

        $ticket->update(['important_note' => $validated['important_note']]);

        return back()->with('success', 'Nota importante actualizada.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return redirect()->route('tickets.index')->with('success', 'Ticket eliminado.');
    }

    public function storeEvidence(Request $request, Ticket $ticket)
    {
        $request->validate([
            'files' => 'required|array', 
            'files.*' => 'file|max:20480'
        ]);
        
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $ticket->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('ticket_evidence');
                } else {
                    $ticket->addMedia($file)->toMediaCollection('ticket_evidence');
                }
            }
        }
        return back()->with('success', 'Archivo general agregado.');
    }

    public function destroyEvidence($mediaId)
    {
        Media::findOrFail($mediaId)->delete();
        return back()->with('success', 'Archivo eliminado.');
    }

    public function evidenceTemplate(Ticket $ticket)
    {
        $ticket->load([
            'customer',
            'branch',
            'tasks.media',
            'media',
            'budget.customer.media',
        ]);

        // Keep tasks in their natural order (as shown in the task list),
        // not sorted by start_date — the user controls the visual order.
        // Media within each task is ordered by order_column via the model's media() override.

        return Inertia::render('Tickets/EvidenceTemplate', [
            'ticket' => $ticket,
        ]);
    }
}