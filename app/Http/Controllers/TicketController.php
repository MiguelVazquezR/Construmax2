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

        $query = Ticket::with(['customer', 'contact', 'branch', 'tasks.assignee', 'budget']);

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
            'filters' => [
                'folio' => $request->input('folio'),
                'customer' => $request->input('customer'),
                'region' => $request->input('region'),
                'priority' => $request->input('priority'),
                'technician' => $request->input('technician'),
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
            'name' => 'required|string|max:255',
            'service_type' => 'required|string|max:255',
            'duration' => 'nullable|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
            'priority' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
            'task_template_id' => 'nullable|exists:task_templates,id',
        ]);

        $ticket = Ticket::create($validated); //status Borrador por defecto

        if (!empty($validated['task_template_id']) && !empty($validated['technicians'])) {
            $ticket->generateTasksFromTemplate($validated['task_template_id'], $validated['technicians']);
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
            'budget.concepts',
            'budget.payments',
            'budget.responsible',
            'budget.technicianPayments.technician.technician',
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

    public function edit(Ticket $ticket)
    {
        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket->load(['customer', 'contact', 'branch']),
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
            'name' => 'required|string|max:255',
            'service_type' => 'required|string|max:255',
            'duration' => 'nullable|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
            'priority' => 'required|string',
            'status' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
        ]);

        $ticket->update($validated);

        return redirect()->route('tickets.show', $ticket->id)->with('success', 'Ticket actualizado.');
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
}