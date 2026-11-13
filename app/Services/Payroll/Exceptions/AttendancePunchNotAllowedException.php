<?php

namespace App\Services\Payroll\Exceptions;

use Exception;

/**
 * The requested punch does not follow the daily attendance sequence.
 */
class AttendancePunchNotAllowedException extends Exception
{
    /**
     * @param  array<int, array{
     *     type: string,
     *     label: string,
     *     allowed: bool,
     *     reason: string|null,
     *     last_registered_at: string|null
     * }>  $options
     */
    public function __construct(
        public readonly string $punchType,
        string $message,
        public readonly ?string $lastRegisteredAt = null,
        public readonly array $options = [],
    ) {
        parent::__construct($message);
    }
}
