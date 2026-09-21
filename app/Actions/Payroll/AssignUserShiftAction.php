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
 * department assignments keep working as a fallback.
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
        if ($shiftId === null || $shiftId <= 0 || ! Shift::query()->whereKey($shiftId)->exists()) {
            return null;
        }

        $current = ShiftAssignment::currentFor($user);

        if ($current) {
            if ((int) $current->shift_id !== $shiftId) {
                $current->update(['shift_id' => $shiftId, 'is_active' => true]);
            }

            return $current;
        }

        $startDate = $user->payrollProfile?->hire_date;

        return ShiftAssignment::create([
            'user_id' => $user->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $shiftId,
            'start_date' => $startDate?->toDateString() ?? CarbonImmutable::today()->toDateString(),
            'is_active' => true,
        ]);
    }
}
