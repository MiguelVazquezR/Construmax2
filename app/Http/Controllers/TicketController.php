<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Customer;
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
        $sort = $request->input('sort', 'delay'); 

        $query = Ticket::with(['customer', 'contact', 'branch', 'tasks.assignee', 'budget.latestCatalog', 'seller']);

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

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // ORDENAMIENTO
        if ($sort === 'start_date') {
            $query->orderBy('scheduled_start', 'desc');
        } else {
            $query->orderByRaw("CASE WHEN status IN ('Ejecutado', 'Facturado', 'Pagado', 'Cancelado') THEN 2 ELSE 1 END")
                  ->orderBy('scheduled_end', 'asc');
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $query->paginate($perPage)->withQueryString(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'technicians' => User::whereHas('technician')->orderBy('name')->get(['id', 'name']),
            'sellers' => User::whereHas('ticketsAsSeller')->orderBy('name')->get(['id', 'name']),
            'canViewAll' => $request->user()->can('tickets.index-all'),
            'filters' => [
                'folio' => $request->input('folio'),
                'customer' => $request->input('customer'),
                'region' => $request->input('region'),
                'priority' => $request->input('priority'),
                'technician' => $request->input('technician'),
                'seller' => $request->input('seller'),
                'status' => $request->input('status', 'all'),
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

        if (!empty($validated['task_template_id']) && !empty($validated['technicians'])) {
            $ticket->generateTasksFromTemplate($validated['task_template_id'], $validated['technicians']);
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
            'media'
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
        ]);

        $oldTechnicians = $ticket->technicians ?? [];
        $ticket->update($validated);
        $newTechnicians = $ticket->technicians ?? [];

        // When a technician is replaced, reassign all tasks and payments
        // from the removed technician to the new one
        $this->reassignTechnicianData($ticket, $oldTechnicians, $newTechnicians);

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

        $ticket->tasks = $ticket->tasks->sortBy('start_date');

        return Inertia::render('Tickets/EvidenceTemplate', [
            'ticket' => $ticket,
        ]);
    }
}