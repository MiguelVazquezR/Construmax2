<?php

namespace App\Actions\FieldWork;

use App\Models\FieldWorkSchedule;
use App\Services\FieldWork\FieldWorkScheduleService;

class UpdateFieldWorkScheduleAction
{
    public function __construct(
        private readonly FieldWorkScheduleService $scheduleService,
    ) {}

    public function execute(FieldWorkSchedule $schedule, array $data): FieldWorkSchedule
    {
        $schedule->update([
            'ticket_id'  => $data['ticket_id'] ?? $schedule->ticket_id,
            'start_time' => $data['start_time'] ?? $schedule->start_time,
            'end_time'   => $data['end_time'] ?? $schedule->end_time,
            'color'      => $data['color'] ?? $schedule->color,
            'notes'      => array_key_exists('notes', $data) ? $data['notes'] : $schedule->notes,
        ]);

        // Re-sync task timestamps on update
        $this->scheduleService->syncTaskTimestamps($schedule);

        return $schedule->fresh();
    }
}
