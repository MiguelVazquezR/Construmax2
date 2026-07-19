<?php

namespace App\Actions\FieldWork;

use App\Models\FieldWorkSchedule;
use App\Services\FieldWork\FieldWorkScheduleService;

class DeleteFieldWorkScheduleAction
{
    public function __construct(
        private readonly FieldWorkScheduleService $scheduleService,
    ) {}

    public function execute(FieldWorkSchedule $schedule): void
    {
        $ticketId = $schedule->ticket_id;

        $schedule->delete();

        // Clear task timestamps when schedule is removed
        $this->scheduleService->clearTaskTimestamps($ticketId);
    }
}
