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
        $sort = $request->input('sort', 'delay'); // Por defecto ordenamos por "Atraso/Urgencia"

        $query = Ticket::with(['budget.customer', 'responsible', 'tasks.assignee']);

        // Filtros de búsqueda y estado
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('budget', function($b) use ($search) {
                      $b->where('service_type', 'like', "%{$search}%")
                        ->orWhereHas('customer', function($c) use ($search) {
                            $c->where('name', 'like', "%{$search}%");
                        });
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
            // Ordenar por fecha de inicio (Lo más nuevo primero)
            $query->orderBy('scheduled_start', 'desc');
        } else {
            // Ordenar por "Atraso" (Urgencia)
            // 1. Tickets NO completados van primero
            // 2. Dentro de los no completados, ordenamos por fecha de fin ASC (Los que vencieron hace más tiempo o vencen pronto van arriba)
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
        $budgets = Budget::whereIn('status', ['Facturado', 'Trabajo en proceso', 'Pagado', 'Presupuesto enviado'])
            ->with('customer')
            ->doesntHave('ticket')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Tickets/Create', [
            'budgets' => $budgets,
            'users' => User::has('technician')->with('technician')->get(),
            'customers' => Customer::where('is_active', true)->with('contacts')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'budget_id' => 'required|exists:budgets,id|unique:tickets,budget_id',
            'user_id' => 'required|exists:users,id',
            'priority' => 'required|string',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'instructions' => 'nullable|string',
        ]);

        $ticket = Ticket::create([
            'budget_id' => $validated['budget_id'],
            'user_id' => $validated['user_id'],
            'priority' => $validated['priority'],
            'status' => 'Programado',
            'scheduled_start' => $validated['scheduled_start'],
            'scheduled_end' => $validated['scheduled_end'],
            'instructions' => $validated['instructions'],
        ]);

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket operativo generado correctamente.');
    }

    public function storeFromBudget(Request $request, Budget $budget)
    {
        if ($budget->ticket) {
            return back()->with('error', 'Este presupuesto ya tiene un ticket asociado.');
        }

        $ticket = Ticket::create([
            'budget_id' => $budget->id,
            'user_id' => $budget->user_id,
            'priority' => $budget->priority,
            'status' => 'Programado',
            'scheduled_start' => now(),
            'scheduled_end' => now()->addWeeks(2),
            'instructions' => 'Ticket generado automáticamente a partir del Presupuesto #' . $budget->id . '. ' . $budget->name,
        ]);

        return back()->with('success', 'Ticket generado automáticamente.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'budget.customer', 
            'budget.contact', 
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
            'users' => User::has('technician')->with('technician')->get(),
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
            'ticket' => $ticket->load('budget'),
            'users' => User::has('technician')->with('technician')->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
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