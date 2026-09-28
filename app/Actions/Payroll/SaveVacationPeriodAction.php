<?php

namespace App\Actions\Payroll;

use App\Models\User;
use App\Models\VacationPeriod;

class SaveVacationPeriodAction
{
    /**
     * Create or update a vacation period. Editing the days marks the row as
     * customized so the automatic sync stops overwriting them; marking the
     * premium keeps the calculated days refreshing.
     */
    public function execute(User $user, array $data, User $actor, ?VacationPeriod $period = null): VacationPeriod
    {
        $values = [
            'year_number' => $data['year_number'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'entitled_days' => $data['entitled_days'],
            'accrued_days' => $data['accrued_days'],
            'taken_days' => $data['taken_days'],
            'is_customized' => $this->daysChanged($period, $data) || (bool) $period?->is_customized,
            'premium_paid_at' => ! empty($data['premium_paid'])
                ? ($data['premium_paid_at'] ?? now()->toDateString())
                : null,
        ];

        if ($period !== null) {
            // The (user, year) pair is unique even between deleted rows: the
            // deleted one is discarded so the target year can be reused.
            VacationPeriod::onlyTrashed()
                ->forUser($period->user_id)
                ->where('year_number', $data['year_number'])
                ->whereKeyNot($period->id)
                ->forceDelete();

            $period->update($values);

            return $period;
        }

        // Re-adding a deleted year restores its row instead of failing the
        // unique (user, year) constraint.
        $deleted = VacationPeriod::onlyTrashed()
            ->forUser($user->id)
            ->where('year_number', $data['year_number'])
            ->first();

        if ($deleted !== null) {
            $deleted->restore();
            $deleted->update($values);

            return $deleted;
        }

        return VacationPeriod::create([
            'user_id' => $user->id,
            'created_by' => $actor->id,
            ...$values,
        ]);
    }

    /**
     * True when the submitted days differ from the stored ones. Marking the
     * premium as paid does not freeze the days: a row keeps refreshing until
     * the payroll team actually edits a quantity.
     */
    private function daysChanged(?VacationPeriod $period, array $data): bool
    {
        if ($period === null) {
            return true;
        }

        return round((float) $period->entitled_days, 2) !== round((float) $data['entitled_days'], 2)
            || round((float) $period->accrued_days, 2) !== round((float) $data['accrued_days'], 2)
            || round((float) $period->taken_days, 2) !== round((float) $data['taken_days'], 2);
    }
}
