<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketTask;
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
            'user_id' => 'nullable|exists:users,id',
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
            'user_id' => 'nullable|exists:users,id',
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
        $ticket->updateStatusBasedOnTasks(); // Recalcular progreso
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
            'file' => 'required|file|image|max:10240', // 10MB max
        ]);

        if ($request->hasFile('file')) {
            $task->addMediaFromRequest('file')->toMediaCollection('task_evidence');
        }

        return back()->with('success', 'Evidencia subida.');
    }

    // --- GESTIÓN PÚBLICA (SIGNED URL) ---

    public function publicShow(Request $request, TicketTask $task)
    {
        // Cargar relaciones
        $task->load(['media', 'assignee', 'ticket.budget.customer']);

        // Transformar media para incluir URL firmada de borrado
        // Esto es crucial para permitir borrar SOLO las imágenes de esta tarea
        $task->media->transform(function ($media) {
            $media->delete_url = URL::signedRoute('tasks.public.evidence.destroy', ['media' => $media->id]);
            $media->url = $media->getUrl(); // Asegurar que la URL pública esté disponible
            return $media;
        });

        return Inertia::render('Tickets/PublicTask', [
            'task' => $task,
            'ticket' => $task->ticket,
            // Pasamos las URLs de acción manteniendo la firma
            'urls' => [
                'toggle' => URL::signedRoute('tasks.public.toggle', ['task' => $task->id]),
                'evidence' => URL::signedRoute('tasks.public.evidence', ['task' => $task->id]),
            ]
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
        // Al estar bajo middleware 'signed', la URL es segura y temporal
        $media = Media::findOrFail($mediaId);
        $media->delete();
        
        return back()->with('success', 'Evidencia eliminada correctamente.');
    }
}