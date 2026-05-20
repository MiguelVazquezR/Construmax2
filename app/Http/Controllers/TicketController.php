<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Budget;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sort = $request->input('sort', 'delay'); 

        // Ahora cargamos la relación directa con customer y evitamos buscar por budget
        $query = Ticket::with(['customer', 'responsible', 'tasks.assignee']);

        // Filtros de búsqueda y estado
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%") // Busca por nombre del proyecto
                  ->orWhere('service_type', 'like', "%{$search}%") // Busca por tipo de servicio
                  ->orWhereHas('customer', function($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('responsible', function($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // --- LÓGICA DE ORDENAMIENTO ---
        if ($sort === 'start_date') {
            $query->orderBy('scheduled_start', 'desc');
        } else {
            $query->orderByRaw("CASE WHEN status = 'Completado' OR status = 'Cancelado' THEN 2 ELSE 1 END")
                  ->orderBy('scheduled_end', 'asc');
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'perPage', 'sort']),
        ]);
    }

     public function create()
    {
        return Inertia::render('Tickets/Create', [
            // Enviamos TODOS los usuarios activos (excepto soporte) cargando sus relaciones
            'users' => User::where('id', '!=', 1)->with(['employee', 'technician'])->get(),
            // Para poder seleccionar al cliente desde la vista de creación de ticket
            'customers' => Customer::where('is_active', true)->with('contacts')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_contact_id' => 'required|exists:customer_contacts,id',
            'branch' => 'nullable|string',
            'name' => 'required|string|max:255',
            'service_type' => 'required|string|max:255',
            'duration' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'priority' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
        ]);

        $ticket = Ticket::create(array_merge($validated, [
            'status' => 'Programado'
        ]));

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket operativo generado correctamente.');
    }

    public function show(Ticket $ticket)
    {
        // Cargamos también los presupuestos generados a partir de este ticket
        $ticket->load([
            'customer', 
            'contact', 
            'budgets', // <- Relación inversa para ver los presupuestos desde el ticket
            'responsible', 
            'tasks.assignee', 
            'tasks.media', 
            'media'
        ]);
        
        $ticket->append('progress');

        // Generar enlace de "Orden de Trabajo" para cada tarea
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
            // Enviamos TODOS los usuarios para poder asignarlos a las tareas operativas
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
            'ticket' => $ticket->load(['customer', 'contact']),
            'users' => User::where('id', '!=', 1)->with(['employee', 'technician'])->get(),
            'customers' => Customer::where('is_active', true)->with('contacts')->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_contact_id' => 'required|exists:customer_contacts,id',
            'branch' => 'nullable|string',
            'name' => 'required|string|max:255',
            'service_type' => 'required|string|max:255',
            'duration' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
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

    // --- EVIDENCIAS GENERALES ---
    public function storeEvidence(Request $request, Ticket $ticket)
    {
        $request->validate([
            'files' => 'required|array', 
            'files.*' => 'file|max:20480'
        ]);
        
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $ticket->addMedia($file)->toMediaCollection('ticket_evidence');
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