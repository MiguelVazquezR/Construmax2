<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceLog;
use App\Services\Payroll\AttendanceService;

/**
 * Success payload shown to the collaborator after a punch: who registered,
 * what type, at what time and which punch is expected next.
 *
 * The keys are snake_case because the web kiosk already consumes them.
 */
class BuildPunchResponseAction
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @return array{
     *     user_name: string,
     *     employee_number: string|null,
     *     type: string,
     *     type_label: string,
     *     punched_at: string,
     *     date_label: string,
     *     similarity?: float,
     *     suggested_next: string|null,
     *     suggested_next_label: string|null,
     * }
     */
    public function execute(AttendanceLog $log, ?float $similarity = null): array
    {
        $profile = $log->user?->payrollProfile;
        $suggestedNext = $log->user
            ? $this->attendanceService->suggestedTypeFor($log->user)
            : null;

        $payload = [
            'user_name' => (string) $log->user?->name,
            'employee_number' => $profile?->employee_number,
            'type' => $log->type,
            'type_label' => $log->type_label,
            'punched_at' => $log->punched_at->format('H:i'),
            'date_label' => $log->punched_at->format('d/m/Y'),
        ];

        if ($similarity !== null) {
            $payload['similarity'] = round($similarity, 2);
        }

        return [
            ...$payload,
            'suggested_next' => $suggestedNext,
            'suggested_next_label' => $suggestedNext ? (AttendanceLog::TYPES[$suggestedNext] ?? null) : null,
        ];
    }
}
