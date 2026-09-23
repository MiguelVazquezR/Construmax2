<?php

namespace App\Actions\Payroll;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Assigns (or replaces) the individual shift of a collaborator from the user
 * or technician form. It only touches the individual fixed assignment; the
 * department assignments keep working as a fallback. It also keeps the payroll
 * profile's daily hours aligned with the assigned schedule.
 */
class AssignUserShiftAction
{
    /**
     * Handles the "shift_id" field submitted from a user or technician form.
     * The field is only applied with the payroll.profiles.manage permission.
     */
    public function executeFromForm(Request $request, User $user, array $validated): ?ShiftAssignment
    {
        if (! array_key_exists('shift_id', $validated) || ! $request->user()->can('payroll.profiles.manage')) {
            return null;
        }

        $shiftId = $validated['shift_id'] !== null ? (int) $validated['shift_id'] : null;

        return $this->execute($user, $shiftId);
    }

    public function execute(User $user, ?int $shiftId): ?ShiftAssignment
    {
        $shift = $shiftId !== null && $shiftId > 0
            ? Shift::query()->find($shiftId)
            : null;

        if (! $shift) {
            return null;
        }

        $current = ShiftAssignment::currentFor($user);

        if ($current) {
            if ((int) $current->shift_id !== (int) $shift->id) {
                $current->update(['shift_id' => $shift->id, 'is_active' => true]);
            }

            $assignment = $current;
        } else {
            $startDate = $user->payrollProfile?->hire_date;

            $assignment = ShiftAssignment::create([
                'user_id' => $user->id,
                'type' => ShiftAssignment::TYPE_FIXED,
                'shift_id' => $shift->id,
                'start_date' => $startDate?->toDateString() ?? CarbonImmutable::today()->toDateString(),
                'is_active' => true,
            ]);
        }

        $this->syncProfileDailyHours($user, $shift);

        return $assignment;
    }

    /**
     * The payroll profile stores a single daily-hours figure for the minute
     * rate (overtime and late discounts). With the manual "hours per day"
     * field removed from the forms, it follows the assigned schedule.
     */
    private function syncProfileDailyHours(User $user, Shift $shift): void
    {
        $hours = $shift->representativeDailyHours();

        if ($hours <= 0) {
            return;
        }

        $user->payrollProfile()->first()?->update(['daily_hours' => $hours]);
    }
}
