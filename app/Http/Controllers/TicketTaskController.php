<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TicketTaskController extends Controller
{
    // --- GESTIÓN INTERNA (AUTH) ---

    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        $ticket->tasks()->create($validated);
        $ticket->updateStatusBasedOnTasks();

        return back()->with('success', 'Tarea creada.');
    }

    public function update(Request $request, TicketTask $task)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);
        return back()->with('success', 'Tarea actualizada.');
    }

    public function destroy(TicketTask $task)
    {
        $ticket = $task->ticket;
        $task->delete();
        $ticket->updateStatusBasedOnTasks(); 
        return back()->with('success', 'Tarea eliminada.');
    }

    public function toggleComplete(TicketTask $task)
    {
        $newStatus = $task->status === 'Completada' ? 'Pendiente' : 'Completada';
        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'Completada' ? now() : null,
        ]);
        
        $task->ticket->updateStatusBasedOnTasks();

        return back()->with('success', 'Estatus de tarea actualizado.');
    }

    public function storeEvidence(Request $request, TicketTask $task)
    {
        $request->validate([
            'file' => 'required|file|image|max:10240', 
        ]);

        if ($request->hasFile('file')) {
            $task->addMediaFromRequest('file')->toMediaCollection('task_evidence');
        }

        return back()->with('success', 'Evidencia subida.');
    }

    // --- GESTIÓN PÚBLICA (SIGNED URL) ---

    // NUEVO MÉTODO: Muestra todas las tareas asignadas al técnico en este ticket
    public function publicJobOrder(Request $request, Ticket $ticket, User $user)
    {
        // Cargar datos del ticket y cliente
        $ticket->load(['budget.customer', 'budget.contact']);

        // Obtener tareas SOLO asignadas a este usuario, ordenadas cronológicamente
        $tasks = $ticket->tasks()
            ->where('user_id', $user->id)
            ->with(['media'])
            ->orderBy('start_date', 'asc') // Orden cronológico vital para la secuencia
            ->get();

        // Transformar tareas para incluir URLs de acción firmadas individualmente
        $tasks->transform(function ($task) {
            
            // URLs para las evidencias existentes
            $task->media->transform(function ($media) {
                $media->delete_url = URL::signedRoute('tasks.public.evidence.destroy', ['media' => $media->id]);
                $media->url = $media->getUrl();
                return $media;
            });

            // URLs de acción para la tarea
            $task->urls = [
                'toggle' => URL::signedRoute('tasks.public.toggle', ['task' => $task->id]),
                'evidence' => URL::signedRoute('tasks.public.evidence', ['task' => $task->id]),
            ];

            return $task;
        });

        return Inertia::render('Tickets/PublicTask', [ // Reutilizamos el nombre de archivo, pero la vista cambiará
            'ticket' => $ticket,
            'technician' => $user,
            'tasks' => $tasks,
        ]);
    }

    public function publicToggle(Request $request, TicketTask $task)
    {
        $this->toggleComplete($task);
        return back()->with('success', 'Estatus actualizado correctamente.');
    }

    public function publicEvidence(Request $request, TicketTask $task)
    {
        $request->validate([
            'file' => 'required|file|image|max:10240',
        ]);

        if ($request->hasFile('file')) {
            $task->addMediaFromRequest('file')->toMediaCollection('task_evidence');
        }

        return back()->with('success', 'Evidencia compartida correctamente.');
    }

    public function publicDestroyEvidence(Request $request, $mediaId)
    {
        $media = Media::findOrFail($mediaId);
        $media->delete();
        
        return back()->with('success', 'Evidencia eliminada correctamente.');
    }
}