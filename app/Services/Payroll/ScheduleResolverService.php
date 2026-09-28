<?php

namespace App\Services\Payroll;

use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ScheduleResolverService
{
    /**
     * Effective shift for a collaborator on a date, resolving the assignment
     * (individual first, then department) and weekly rotations.
     */
    public function resolveFor(User $user, CarbonInterface $date): ?ResolvedSchedule
    {
        $assignment = $this->assignmentFor($user, $date);

        if (! $assignment) {
            return null;
        }

        $shift = $assignment->shiftForDate($date);

        if (! $shift || ! $shift->is_active) {
            return null;
        }

        return new ResolvedSchedule($shift, $assignment, CarbonImmutable::parse($date->toDateString()));
    }

    /**
     * Applicable assignment for the user on the given date, following the
     * precedence: individual assignment > department assignment.
     */
    public function assignmentFor(User $user, CarbonInterface $date): ?ShiftAssignment
    {
        $base = ShiftAssignment::query()
            ->active()
            ->effectiveOn($date);

        $individual = (clone $base)
            ->where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->first();

        if ($individual) {
            return $individual;
        }

        $department = $user->employee?->department;

        if (! $department) {
            return null;
        }

        return (clone $base)
            ->whereNull('user_id')
            ->where('department', $department)
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Weekly grid (ISO weekday => resolved schedule or null) starting on the
     * week of the given date. Used by the schedule drawers.
     *
     * @return array<int, ?ResolvedSchedule>
     */
    public function weeklyScheduleFor(User $user, CarbonInterface $weekStart): array
    {
        $monday = CarbonImmutable::parse($weekStart->toDateString())->startOfWeek();
        $grid = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $monday->addDays($offset);
            $grid[$day->isoWeekday()] = $this->resolveFor($user, $day);
        }

        return $grid;
    }
}
