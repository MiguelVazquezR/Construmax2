<?php

namespace App\Services\Payroll;

use App\Models\Incident;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Models\VacationAdjustment;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VacationService
{
    public function __construct(
        private readonly ScheduleResolverService $scheduleResolver,
        private readonly HolidayService $holidayService,
    ) {}

    /**
     * Vacation days granted by seniority (LFT art. 76, 2023 reform):
     * 12 days from the first year, +2 per year up to 20 days, then +2 every
     * 5 years (22, 24, 26, ...).
     */
    public function entitledDaysForYearOfService(int $yearOfService): int
    {
        if ($yearOfService <= 1) {
            return 12;
        }

        if ($yearOfService <= 5) {
            return 12 + 2 * ($yearOfService - 1);
        }

        return 20 + 2 * (int) ceil(($yearOfService - 5) / 5);
    }

    /**
     * Balance breakdown by service year ("temporada") plus totals.
     *
     * Accrual is proportional to the weeks completed in each season
     * (entitled days / 52 per week). Approved requests consume days FIFO
     * (oldest season first) and unused leftovers expire after the configured
     * carryover window (18 months by default, art. 76 LFT).
     *
     * Manual movements (initial balance, granted days and corrections) never
     * expire and are consumed after every season is exhausted, so the LFT days
     * are always used before them.
     *
     * @return array{
     *     hire_date: ?string,
     *     current_season: int,
     *     entitled_days: float,
     *     accrued_days: float,
     *     taken_days: float,
     *     pending_days: float,
     *     adjustment_days: float,
     *     adjustment_available_days: float,
     *     available_days: float,
     *     seasons: array<int, array<string, mixed>>,
     * }
     */
    public function balanceFor(User $user, ?CarbonInterface $now = null): array
    {
        $now = $now
            ? CarbonImmutable::parse($now->toDateString())
            : CarbonImmutable::today();

        $settings = PayrollSetting::current();
        $hire = $user->payrollProfile?->hire_date;

        $adjustmentDays = $this->adjustmentDaysFor($user);
        $pool = $adjustmentDays;

        if (! $hire) {
            return $this->emptyBalance(
                adjustmentDays: $adjustmentDays,
                adjustmentAvailable: $pool - $this->approvedDaysFor($user),
                pendingDays: $this->pendingDaysFor($user),
            );
        }

        $hire = CarbonImmutable::parse($hire->toDateString());

        $seasons = [];

        for ($season = 1; $season <= 60; $season++) {
            $start = $hire->addYears($season - 1);

            if ($start->greaterThan($now)) {
                break;
            }

            $end = $hire->addYears($season)->subDay();
            $entitled = $this->entitledDaysForYearOfService($season);
            $effectiveEnd = $end->lessThan($now) ? $end : $now;
            $weeks = intdiv(max(0, (int) $start->diffInDays($effectiveEnd)), 7);
            $accrued = min((float) $entitled, round($entitled * $weeks / 52, 2));

            $seasons[$season] = [
                'season' => $season,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'is_current' => $now->lessThanOrEqualTo($end),
                'entitled' => (float) $entitled,
                'accrued' => $accrued,
                'taken' => 0.0,
                'expired' => 0.0,
                'available' => 0.0,
                'expiry_date' => $end->addMonthsNoOverflow((int) $settings->vacation_carryover_months)->toDateString(),
            ];
        }

        if ($seasons === []) {
            return $this->emptyBalance(
                hireDate: $hire->toDateString(),
                adjustmentDays: $adjustmentDays,
                adjustmentAvailable: $pool - $this->approvedDaysFor($user),
                pendingDays: $this->pendingDaysFor($user),
            );
        }

        // FIFO consumption of the approved requests: oldest season first and,
        // once every season is exhausted, the manual movements of the balance.
        $approved = VacationRequest::query()
            ->forUser($user->id)
            ->approved()
            ->orderBy('start_date')
            ->get();

        foreach ($approved as $request) {
            $remaining = (float) $request->days;

            foreach ($seasons as $key => $season) {
                if ($remaining <= 0) {
                    break;
                }

                $space = $season['accrued'] - $season['taken'];

                if ($space <= 0) {
                    continue;
                }

                $used = min($space, $remaining);
                $seasons[$key]['taken'] = round($seasons[$key]['taken'] + $used, 2);
                $remaining -= $used;
            }

            if ($remaining > 0 && $pool > 0) {
                $pool = round($pool - min($pool, $remaining), 2);
            }
        }

        $totalAccrued = 0.0;
        $totalTaken = 0.0;
        $totalAvailable = 0.0;

        foreach ($seasons as $key => $season) {
            $leftover = max(0.0, round($season['accrued'] - $season['taken'], 2));
            $expired = $now->greaterThan(CarbonImmutable::parse($season['expiry_date'])) ? $leftover : 0.0;
            $available = round($leftover - $expired, 2);

            $seasons[$key]['expired'] = $expired;
            $seasons[$key]['available'] = $available;

            $totalAccrued += $season['accrued'];
            $totalTaken += $season['taken'];
            $totalAvailable += $available;
        }

        $current = max(array_keys($seasons));

        return [
            'hire_date' => $hire->toDateString(),
            'current_season' => $current,
            'entitled_days' => (float) $this->entitledDaysForYearOfService($current),
            'accrued_days' => round($totalAccrued, 2),
            'taken_days' => round($totalTaken, 2),
            'pending_days' => $this->pendingDaysFor($user),
            'adjustment_days' => $adjustmentDays,
            'adjustment_available_days' => round($pool, 2),
            'available_days' => round(max(0.0, $totalAvailable + $pool), 2),
            'seasons' => array_values($seasons),
        ];
    }

    /**
     * Net sum of the manual movements registered for the user (signed).
     */
    public function adjustmentDaysFor(User $user): float
    {
        return round((float) VacationAdjustment::query()->forUser($user->id)->sum('days'), 2);
    }

    private function approvedDaysFor(User $user): float
    {
        return (float) VacationRequest::query()->forUser($user->id)->approved()->sum('days');
    }

    private function pendingDaysFor(User $user): float
    {
        return round((float) VacationRequest::query()->forUser($user->id)->pending()->sum('days'), 2);
    }

    /**
     * Working days inside a date range, using the resolved schedule and
     * excluding holidays and rest days. Days without an assigned schedule
     * count as calendar days (minus holidays).
     */
    public function workingDaysFor(User $user, CarbonInterface $start, CarbonInterface $end): float
    {
        $days = 0.0;
        $cursor = CarbonImmutable::parse($start->toDateString());
        $last = CarbonImmutable::parse($end->toDateString());

        while ($cursor->lessThanOrEqualTo($last)) {
            if (! $this->holidayService->isHoliday($cursor)) {
                $schedule = $this->scheduleResolver->resolveFor($user, $cursor);

                if ($schedule === null || $schedule->isWorkday()) {
                    $days += 1;
                }
            }

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Validate a vacation request against the balance, the configured minimum
     * and the overlapping requests/incidents. Returns the computed days.
     *
     * @throws ValidationException
     */
    public function validateRequest(User $user, string $start, string $end, ?int $ignoreRequestId = null): float
    {
        $startDate = CarbonImmutable::parse($start);
        $endDate = CarbonImmutable::parse($end);

        if ($endDate->lessThan($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha final no puede ser anterior a la inicial.',
            ]);
        }

        $days = $this->workingDaysFor($user, $startDate, $endDate);

        if ($days <= 0) {
            throw ValidationException::withMessages([
                'start_date' => 'El rango seleccionado no contiene días laborables.',
            ]);
        }

        $balance = $this->balanceFor($user);
        $minimum = (float) PayrollSetting::current()->vacation_min_days_to_request;

        if ($balance['available_days'] < $minimum) {
            throw ValidationException::withMessages([
                'balance' => 'Necesitas al menos '.$minimum.' día(s) acumulados para solicitar vacaciones.',
            ]);
        }

        if ($days > $balance['available_days']) {
            throw ValidationException::withMessages([
                'balance' => 'Solo tienes '.$balance['available_days'].' día(s) disponibles y estás solicitando '.$days.'.',
            ]);
        }

        $overlapsRequest = VacationRequest::query()
            ->forUser($user->id)
            ->blocking()
            ->when($ignoreRequestId, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->overlapping($startDate, $endDate)
            ->exists();

        if ($overlapsRequest) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe una solicitud de vacaciones en ese rango de fechas.',
            ]);
        }

        $overlapsIncident = Incident::query()
            ->forUser($user->id)
            ->approved()
            ->where('type', Incident::TYPE_VACATION)
            ->overlapping($startDate, $endDate)
            ->exists();

        if ($overlapsIncident) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existen vacaciones registradas en ese rango de fechas.',
            ]);
        }

        return $days;
    }

    /**
     * @return array{hire_date: ?string, current_season: int, entitled_days: float, accrued_days: float, taken_days: float, pending_days: float, adjustment_days: float, adjustment_available_days: float, available_days: float, seasons: array<int, array<string, mixed>>}
     */
    private function emptyBalance(
        ?string $hireDate = null,
        float $adjustmentDays = 0.0,
        float $adjustmentAvailable = 0.0,
        float $pendingDays = 0.0,
    ): array {
        return [
            'hire_date' => $hireDate,
            'current_season' => 0,
            'entitled_days' => 0.0,
            'accrued_days' => 0.0,
            'taken_days' => 0.0,
            'pending_days' => $pendingDays,
            'adjustment_days' => $adjustmentDays,
            'adjustment_available_days' => round($adjustmentAvailable, 2),
            'available_days' => round(max(0.0, $adjustmentAvailable), 2),
            'seasons' => [],
        ];
    }
}
