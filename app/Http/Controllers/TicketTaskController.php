<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketTask;
use Illuminate\Http\Request;

class TicketTaskController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $ticket->tasks()->create($validated);
        
        // Actualizar estatus del ticket
        $ticket->updateStatusBasedOnTasks();

        return back()->with('success', 'Tarea agregada al cronograma.');
    }

    public function update(Request $request, TicketTask $task)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            if ($task->status !== 'Completada' && $validated['status'] === 'Completada') {
                $task->completed_at = now();
            } elseif ($validated['status'] !== 'Completada') {
                $task->completed_at = null;
            }
        }

        $task->update($validated);

        // Actualizar estatus del ticket padre
        $task->ticket->updateStatusBasedOnTasks();

        return back()->with('success', 'Tarea actualizada.');
    }

    public function toggleComplete(TicketTask $task)
    {
        $isComplete = $task->status === 'Completada';
        
        $task->update([
            'status' => $isComplete ? 'Pendiente' : 'Completada',
            'completed_at' => $isComplete ? null : now(),
        ]);

        // Actualizar estatus del ticket padre (Magia aquí)
        $task->ticket->updateStatusBasedOnTasks();

        return back()->with('success', 'Estado de tarea actualizado.');
    }

    public function destroy(TicketTask $task)
    {
        $ticket = $task->ticket; // Guardar referencia antes de borrar
        $task->delete();
        
        // Actualizar estatus del ticket por si borramos la única tarea pendiente
        $ticket->updateStatusBasedOnTasks();

        return back()->with('success', 'Tarea eliminada.');
    }

    public function storeEvidence(Request $request, TicketTask $task)
    {
        $request->validate(['file' => 'required|image|max:10240']);
        
        if ($request->hasFile('file')) {
            $task->addMediaFromRequest('file')->toMediaCollection('task_evidence');
        }
        return back()->with('success', 'Evidencia de tarea agregada.');
    }
}