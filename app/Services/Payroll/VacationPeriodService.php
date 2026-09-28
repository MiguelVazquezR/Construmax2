<?php

namespace App\Services\Payroll;

use App\Models\User;
use App\Models\VacationPeriod;

class VacationPeriodService
{
    public function __construct(
        private readonly VacationService $vacationService,
    ) {}

    /**
     * Keep the stored periods in sync with the calculated seasons: every
     * started year of service gets a row, and rows that were not customized
     * by the payroll team refresh their days. Soft deleted rows are never
     * regenerated (deleting a period is respected).
     *
     * @param  array<int, array<string, mixed>>|null  $seasons  Seasons from VacationService::balanceFor()
     */
    public function syncFor(User $user, ?array $seasons = null): void
    {
        $seasons ??= $this->vacationService->balanceFor($user)['seasons'];

        if ($seasons === []) {
            return;
        }

        $stored = VacationPeriod::withTrashed()
            ->forUser($user->id)
            ->get()
            ->keyBy('year_number');

        foreach ($seasons as $season) {
            $period = $stored->get($season['season']);

            if ($period?->trashed()) {
                continue;
            }

            $values = [
                'start_date' => $season['start'],
                'end_date' => $season['end'],
                'entitled_days' => $season['entitled'],
                'accrued_days' => $season['accrued'],
                'taken_days' => $season['taken'],
            ];

            if ($period === null) {
                VacationPeriod::create([
                    'user_id' => $user->id,
                    'year_number' => $season['season'],
                    ...$values,
                ]);

                continue;
            }

            if (! $period->is_customized) {
                $period->update($values);
            }
        }
    }

    /**
     * Period row of a service year, syncing the seasons first. Returns null
     * when the payroll team deleted that year.
     */
    public function ensureForYear(User $user, int $yearNumber): ?VacationPeriod
    {
        $this->syncFor($user);

        return VacationPeriod::query()
            ->forUser($user->id)
            ->where('year_number', $yearNumber)
            ->first();
    }

    /**
     * Stored periods of the collaborator, ready for the vacations screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payloadFor(User $user): array
    {
        return VacationPeriod::query()
            ->forUser($user->id)
            ->orderBy('year_number')
            ->get()
            ->map(fn (VacationPeriod $period) => $period->toPayload())
            ->values()
            ->all();
    }
}
