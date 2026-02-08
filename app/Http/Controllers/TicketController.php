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

        return Inertia::render('Tickets/Index', [
            'tickets' => Ticket::with(['budget.customer', 'responsible', 'tasks'])
                ->orderBy('id', 'desc')
                ->paginate($perPage),
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
            'users' => User::where('is_active', true)->get(),
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

    // --- MÉTODO PARA CREACIÓN AUTOMÁTICA DESDE PRESUPUESTO ---
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

        // Generar URLs firmadas para compartir tareas públicamente
        $ticket->tasks->transform(function ($task) {
            $task->share_url = URL::signedRoute('tasks.public.show', ['task' => $task->id]);
            return $task;
        });

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'users' => User::where('is_active', true)->get(),
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
            'users' => User::where('is_active', true)->get(),
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