<?php

namespace App\Actions\FieldWork;

use App\Models\FieldWorkSchedule;
use App\Services\FieldWork\FieldWorkScheduleService;

class CreateFieldWorkScheduleAction
{
    public function __construct(
        private readonly FieldWorkScheduleService $scheduleService,
    ) {}

    public function execute(array $data): FieldWorkSchedule
    {
        $schedule = FieldWorkSchedule::create([
            'ticket_id'  => $data['ticket_id'],
            'user_id'    => auth()->id(),
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
            'color'      => $data['color'] ?? '#409EFF',
            'notes'      => $data['notes'] ?? null,
        ]);

        // Automatically sync task timestamps
        $this->scheduleService->syncTaskTimestamps($schedule);

        return $schedule;
    }
}
