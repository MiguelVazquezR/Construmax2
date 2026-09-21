<?php

namespace App\Services\Payroll;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;

/**
 * Effective shift of a collaborator on a specific date.
 */
final class ResolvedSchedule
{
    public function __construct(
        public readonly Shift $shift,
        public readonly ?ShiftAssignment $assignment,
        public readonly CarbonImmutable $date,
    ) {}

    public function isWorkday(): bool
    {
        return $this->shift->isWorkday($this->date);
    }

    public function expectedStart(): ?CarbonImmutable
    {
        $start = $this->shift->expectedStartFor($this->date);

        return $start ? CarbonImmutable::instance($start) : null;
    }

    public function expectedEnd(): ?CarbonImmutable
    {
        $end = $this->shift->expectedEndFor($this->date);

        return $end ? CarbonImmutable::instance($end) : null;
    }

    public function expectedDailyMinutes(): int
    {
        return $this->shift->expectedDailyMinutes($this->date);
    }

    public function mealIsPaid(): bool
    {
        return (bool) $this->shift->is_meal_paid;
    }

    public function lateToleranceMinutes(int $fallback): int
    {
        return $this->shift->late_tolerance_minutes ?? $fallback;
    }
}
