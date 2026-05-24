<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TicketTaskController extends Controller
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}
    // --- GESTIÓN INTERNA (AUTH) ---

    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date', // Requerido para validar agenda
            'due_date' => 'required|date|after:start_date', // Requerido y debe ser posterior al inicio
        ]);

        // Validar Disponibilidad (advierte si hay cruce, pero permite guardar)
        $warning = $this->checkForOverlaps($validated['user_id'], $validated['start_date'], $validated['due_date']);

        $ticket->tasks()->create($validated);
        $ticket->updateStatusBasedOnTasks();

        if ($warning) {
            return back()->with('warning', $warning);
        }

        return back()->with('success', 'Tarea creada y agendada correctamente.');
    }

    public function update(Request $request, TicketTask $task)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after:start_date',
        ]);

        // Validar Disponibilidad (excluyendo la tarea actual, advierte pero permite guardar)
        $warning = $this->checkForOverlaps($validated['user_id'], $validated['start_date'], $validated['due_date'], $task->id);

        $task->update($validated);

        if ($warning) {
            return back()->with('warning', $warning);
        }

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
            $file = $request->file('file');
            $optimizedPath = $this->imageOptimizer->optimize($file);
            $task->addMedia($optimizedPath)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('task_evidence');
        }

        return back()->with('success', 'Evidencia subida.');
    }

    // --- HELPER: VALIDACIÓN DE TRASLAPE DE HORARIOS ---
    
    /**
     * Check for scheduling overlaps for a given technician.
     * Returns a warning message string if a conflict is found, null otherwise.
     */
    private function checkForOverlaps($userId, $start, $end, $ignoreTaskId = null): ?string
    {
        // Buscamos tareas del MISMO técnico que se crucen en el tiempo
        // Lógica de cruce: (InicioA < FinB) y (FinA > InicioB)
        $conflict = TicketTask::where('user_id', $userId)
            ->where(function($query) use ($start, $end) {
                $query->where('start_date', '<', $end)
                      ->where('due_date', '>', $start);
            })
            ->when($ignoreTaskId, function($query, $id) {
                $query->where('id', '!=', $id);
            })
            ->with(['ticket.budget.customer']) // Cargamos datos para el mensaje de advertencia
            ->first();

        if ($conflict) {
            $techName = User::find($userId)->name ?? 'El técnico';
            $startDate = $conflict->start_date->format('d M - g:ia');
            $endDate = $conflict->due_date->format('d M - g:ia');
            $project = $conflict->ticket->budget->name ?? 'Ticket #' . $conflict->ticket_id;
            $customer = $conflict->ticket->budget->customer->name ?? 'N/A';

            return "Conflicto de agenda: $techName ya tiene la tarea '{$conflict->name}' asignada en este horario ($startDate | $endDate) para el proyecto '$project' ($customer). La tarea se guardó de todos modos.";
        }

        return null;
    }

    // --- GESTIÓN PÚBLICA (SIGNED URL) ---

    public function publicJobOrder(Request $request, Ticket $ticket, User $user)
    {
        $ticket->load(['budget.customer', 'budget.contact']);

        $tasks = $ticket->tasks()
            ->where('user_id', $user->id)
            ->with(['media'])
            ->orderBy('start_date', 'asc') 
            ->get();

        $tasks->transform(function ($task) {
            $task->media->transform(function ($media) {
                $media->delete_url = URL::signedRoute('tasks.public.evidence.destroy', ['media' => $media->id]);
                $media->url = $media->getUrl();
                return $media;
            });

            $task->urls = [
                'toggle' => URL::signedRoute('tasks.public.toggle', ['task' => $task->id]),
                'evidence' => URL::signedRoute('tasks.public.evidence', ['task' => $task->id]),
            ];

            return $task;
        });

        return Inertia::render('Tickets/PublicTask', [ 
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
            $file = $request->file('file');
            $optimizedPath = $this->imageOptimizer->optimize($file);
            $task->addMedia($optimizedPath)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('task_evidence');
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