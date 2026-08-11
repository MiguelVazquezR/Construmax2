<?php

namespace App\Services\FieldWork;

use App\Models\FieldWorkSchedule;
use App\Models\Ticket;
use App\Models\TicketTask;
use Illuminate\Support\Collection;

class FieldWorkScheduleService
{
    /**
     * Sync ticket task timestamps to match the field work schedule.
     *
     * Rules:
     *  - Tasks 1 to N-1  → start_date = schedule's start_time
     *  - Task N (last)   → start_date = schedule's start_time,
     *                       due_date   = schedule's end_time
     */
    public function syncTaskTimestamps(FieldWorkSchedule $schedule): void
    {
        $tasks = $this->getOrderedTasks($schedule->ticket_id);

        if ($tasks->isEmpty()) {
            return;
        }

        $total      = $tasks->count();
        $startTime  = $schedule->start_time;
        $endTime    = $schedule->end_time;

        foreach ($tasks as $index => $task) {
            $isLast = ($index === $total - 1);

            $task->update([
                'start_date' => $startTime,
                'due_date'   => $isLast ? $endTime : $startTime,
            ]);
        }
    }

    /**
     * Revert task timestamps to null when a schedule is deleted.
     */
    public function clearTaskTimestamps(int $ticketId): void
    {
        $tasks = $this->getOrderedTasks($ticketId);

        foreach ($tasks as $task) {
            $task->update([
                'start_date' => null,
                'due_date'   => null,
            ]);
        }
    }

    /**
     * Get tasks for a ticket ordered by ID (creation order).
     */
    private function getOrderedTasks(int $ticketId): Collection
    {
        return TicketTask::where('ticket_id', $ticketId)
            ->orderBy('id')
            ->get();
    }
}
