<?php

namespace App\Http\Controllers;

use App\Actions\FieldWork\CreateFieldWorkScheduleAction;
use App\Actions\FieldWork\DeleteFieldWorkScheduleAction;
use App\Actions\FieldWork\UpdateFieldWorkScheduleAction;
use App\Http\Requests\FieldWork\StoreFieldWorkScheduleRequest;
use App\Http\Requests\FieldWork\UpdateFieldWorkScheduleRequest;
use App\Models\FieldWorkSchedule;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FieldWorkScheduleController extends Controller
{
    public function __construct(
        private readonly CreateFieldWorkScheduleAction $createAction,
        private readonly UpdateFieldWorkScheduleAction $updateAction,
        private readonly DeleteFieldWorkScheduleAction $deleteAction,
    ) {}

    // -----------------------------------------------------------------------
    // API: available tickets for scheduling
    // -----------------------------------------------------------------------

    /**
     * Return tickets eligible for field work scheduling.
     * Criteria: status = 'Proceso de ejecución' AND not already scheduled.
     */
    public function availableTickets(): \Illuminate\Http\JsonResponse
    {
        $scheduledIds = FieldWorkSchedule::pluck('ticket_id');

        $tickets = Ticket::where('status', 'Proceso de ejecución')
            ->whereNotIn('id', $scheduledIds)
            ->with([
                'customer',
                'branch',
                'contact',
                'seller',
                'tasks' => fn ($q) => $q->orderBy('id'),
            ])
            ->get();

        // Resolve all technician IDs to names in one query
        $allTechnicianIds = $tickets->flatMap(fn ($t) => array_merge(
            $t->technicians ?? [],
            $t->assistant_technicians ?? [],
        ))->unique()->filter()->values();

        $technicianNames = User::whereIn('id', $allTechnicianIds)
            ->pluck('name', 'id');

        $result = $tickets->map(fn (Ticket $ticket) => [
            'id'                => $ticket->id,
            'folio'             => $ticket->folio,
            'name'              => $ticket->name,
            'customer_name'     => $ticket->customer?->name,
            'branch_name'       => $ticket->branch?->branch_name,
            'contact_name'      => $ticket->contact?->name,
            'seller_name'       => $ticket->seller?->name,
            'technician_ids'    => $ticket->technicians ?? [],
            'technician_names'  => collect($ticket->technicians ?? [])
                ->map(fn ($id) => $technicianNames->get((int) $id, "ID $id"))
                ->filter()
                ->values()
                ->toArray(),
            'assistant_ids'     => $ticket->assistant_technicians ?? [],
            'first_task_date'   => $ticket->tasks->first()?->start_date?->toDateTimeString(),
            'last_task_date'    => $ticket->tasks->last()?->due_date?->toDateTimeString(),
        ]);

        return response()->json($result);
    }

    /**
     * Return all field work schedules formatted as calendar events.
     * Accepts optional query filters: technician_id, branch_id, is_internal.
     */
    public function events(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = FieldWorkSchedule::with([
            'ticket.customer',
            'ticket.branch',
            'ticket.contact',
            'ticket.seller',
            'creator',
        ]);

        // --- Filter: technician ---
        if ($request->filled('technician_id')) {
            $techId = $request->input('technician_id');
            $query->whereHas('ticket', function ($q) use ($techId) {
                $q->whereJsonContains('technicians', (string) $techId)
                  ->orWhereJsonContains('technicians', (int) $techId)
                  ->orWhereJsonContains('assistant_technicians', (string) $techId)
                  ->orWhereJsonContains('assistant_technicians', (int) $techId);
            });
        }

        // --- Filter: branch ---
        if ($request->filled('branch_id')) {
            $query->whereHas('ticket', function ($q) use ($request) {
                $q->where('customer_branch_id', $request->input('branch_id'));
            });
        }

        // --- Filter: is_internal ---
        if ($request->filled('is_internal')) {
            $isInternal = $request->boolean('is_internal');
            $techUserIds = \App\Models\Technician::where('is_internal', $isInternal)
                ->pluck('user_id')
                ->toArray();

            if (!empty($techUserIds)) {
                $query->whereHas('ticket', function ($q) use ($techUserIds) {
                    $q->where(function ($sub) use ($techUserIds) {
                        foreach ($techUserIds as $uid) {
                            $sub->orWhereJsonContains('technicians', (string) $uid)
                                ->orWhereJsonContains('technicians', (int) $uid)
                                ->orWhereJsonContains('assistant_technicians', (string) $uid)
                                ->orWhereJsonContains('assistant_technicians', (int) $uid);
                        }
                    });
                });
            } else {
                // No technicians match this is_internal value — return empty
                return response()->json([]);
            }
        }

        $schedules = $query->get();

        // Resolve technician names in batch
        $allTechIds = $schedules->flatMap(fn ($s) => array_merge(
            $s->ticket->technicians ?? [],
            $s->ticket->assistant_technicians ?? [],
        ))->unique()->filter()->values();

        $techNames = \App\Models\User::whereIn('id', $allTechIds)->pluck('name', 'id');

        $result = $schedules->map(fn (FieldWorkSchedule $s) => [
            'id'                    => $s->id,
            'title'                 => $s->ticket->name ?? 'Trabajo en campo',
            'start_time'            => $s->start_time->toDateTimeString(),
            'end_time'              => $s->end_time->toDateTimeString(),
            'is_all_day'            => false,
            'color'                 => $s->color,
            'description'           => $s->notes,
            'notes'                 => $s->notes,
            'ticket_id'             => $s->ticket_id,
            'ticket_folio'          => $s->ticket->folio ?? '',
            'ticket_report_number'  => $s->ticket->report_number ?? '',
            'ticket_service_type'   => $s->ticket->service_type ?? '',
            'ticket_status'         => $s->ticket->status ?? null,
            'customer'              => $s->ticket->customer?->name,
            'branch'                => $s->ticket->branch?->branch_name,
            'contact_name'          => $s->ticket->contact?->name,
            'seller_name'           => $s->ticket->seller?->name,
            'technician_names'      => collect($s->ticket->technicians ?? [])
                ->map(fn ($id) => $techNames->get((int) $id, "ID $id"))
                ->filter()
                ->values()
                ->toArray(),
            'user_id'               => $s->user_id,
        ]);

        return response()->json($result);
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    public function store(StoreFieldWorkScheduleRequest $request): RedirectResponse
    {
        $this->createAction->execute($request->validated());

        return back()->with('success', 'Trabajo en campo agendado correctamente.');
    }

    public function update(UpdateFieldWorkScheduleRequest $request, FieldWorkSchedule $schedule): RedirectResponse
    {
        // Only the creator can edit their own schedules
        if ($schedule->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para editar esta agenda.');
        }

        $this->updateAction->execute($schedule, $request->validated());

        return back()->with('success', 'Agenda de trabajo en campo actualizada correctamente.');
    }

    public function destroy(FieldWorkSchedule $schedule): RedirectResponse
    {
        // Only the creator can delete their own schedules
        if ($schedule->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para eliminar esta agenda.');
        }

        $this->deleteAction->execute($schedule);

        return back()->with('success', 'Agenda de trabajo en campo eliminada correctamente.');
    }
}
