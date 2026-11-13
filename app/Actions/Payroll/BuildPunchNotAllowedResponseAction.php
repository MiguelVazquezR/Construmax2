<?php

namespace App\Actions\Payroll;

use App\Services\Payroll\Exceptions\AttendancePunchNotAllowedException;

/**
 * Error payload for a punch that does not follow the daily attendance
 * sequence.
 *
 * The web kiosk and the Android kiosk API share it so both clients receive
 * exactly the same contract: the reason, the rejected type, the last punch of
 * that type and the state of the six punch types.
 */
class BuildPunchNotAllowedResponseAction
{
    /**
     * @return array{
     *     message: string,
     *     code: string,
     *     type: string,
     *     last_registered_at: string|null,
     *     options: array<int, array{
     *         type: string,
     *         label: string,
     *         allowed: bool,
     *         reason: string|null,
     *         last_registered_at: string|null
     *     }>,
     *     errors: array{type: array<int, string>}
     * }
     */
    public function execute(AttendancePunchNotAllowedException $exception): array
    {
        return [
            'message' => $exception->getMessage(),
            'code' => 'attendance_punch_not_allowed',
            'type' => $exception->punchType,
            'last_registered_at' => $exception->lastRegisteredAt,
            'options' => $exception->options,
            'errors' => [
                'type' => [$exception->getMessage()],
            ],
        ];
    }
}
