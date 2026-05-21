<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
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
        $perPage = $request->input('perPage', 20);
        $sort = $request->input('sort', 'delay'); 

        // IMPORTANTE: Cargamos 'contact' para que el Accessor del Folio pueda armarse
        $query = Ticket::with(['customer', 'contact', 'responsible', 'tasks.assignee']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('service_type', 'like', "%{$search}%")
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

        if ($sort === 'start_date') {
            $query->orderBy('scheduled_start', 'desc');
        } else {
            // Ordenar por estatus completados al final
            $query->orderByRaw("CASE WHEN status IN ('Ejecutado', 'Facturado', 'Pagado', 'Cancelado') THEN 2 ELSE 1 END")
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
            'users' => User::where('id', '!=', 1)->with(['employee', 'technician'])->get(),
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
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
            'priority' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
        ]);

        $ticket = Ticket::create(array_merge($validated, [
            'status' => 'Borrador' // Creado siempre en Borrador
        ]));

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket operativo generado correctamente.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'customer', 
            'contact', 
            'budgets', 
            'responsible', 
            'tasks.assignee', 
            'tasks.media', 
            'media'
        ]);
        
        $ticket->append('progress', 'folio'); // Aseguramos que el folio viaje al frontend

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