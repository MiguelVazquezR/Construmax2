<?php

namespace App\Services\Payroll;

use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PayrollCalculatorService
{
    public function __construct(
        private readonly AttendanceDayService $attendanceDayService,
    ) {}

    /**
     * Collaborators enabled for payroll on the given date. Their hire date and
     * termination date decide the window in which they belong to the payroll,
     * so a dismissed collaborator stops appearing in the periods that start
     * after the termination date.
     *
     * @return Collection<int, User>
     */
    public function payrollSubjects(CarbonInterface|string|null $onDate = null): Collection
    {
        $onDate = $onDate ?? CarbonImmutable::today();

        return User::query()
            ->whereHas('payrollProfile', fn ($query) => $query->payrollSubjectOn($onDate))
            ->with(['payrollProfile', 'employee'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Day-by-day detail of a collaborator inside a period: live classification
     * for open periods, the frozen payslip days for closed ones. Both cases
     * return the same keys so the UI can render a single table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daysFor(User $user, PayrollPeriod $period): array
    {
        if ($period->isOpen()) {
            return $this->calculateFor($user, $period)['days'];
        }

        $payslip = $period->payslips()->where('user_id', $user->id)->with('days')->first();

        return $payslip
            ? $payslip->days->map(fn ($day) => [
                'date' => $day->date->toDateString(),
                'status' => $day->status,
                'status_label' => null,
                'shift' => null,
                'expected_minutes' => 0,
                'worked_minutes' => $day->worked_minutes,
                'late_minutes' => $day->late_minutes,
                'late_ignored' => false,
                'late_effective_minutes' => $day->late_minutes,
                'overtime_minutes' => $day->overtime_minutes,
                'first_in' => $day->first_in,
                'lunch_start' => $day->lunch_start,
                'lunch_end' => $day->lunch_end,
                'last_out' => $day->last_out,
                'incident_type' => null,
                'holiday_name' => null,
                'pay_fraction' => null,
                'notes' => $day->notes,
            ])->values()->all()
            : [];
    }

    /**
     * Pre-payroll of a collaborator for a period: day-by-day classification
     * plus the earnings/deductions lines and the totals.
     *
     * Rules (all configurable in the payroll settings):
     *  - Scheduled workday with punches: 1 paid day; overtime beyond the
     *    expected minutes accumulates (first hours at the double multiplier,
     *    the excess at the triple one, LFT art. 66-68).
     *  - Scheduled workday without punches and without incident: unpaid day
     *    once the day has passed (an unjustified absence); today and future
     *    days remain pending (*sin registro*) and count as neither paid nor
     *    unpaid.
     *  - Holiday: paid rest day; when worked, an extra day at the configured
     *    multiplier (LFT art. 75).
     *  - Incidents: paid fraction by type (vacations and paid permissions are
     *    full days; medical leaves use their configured percentage; justified
     *    absences, unpaid permissions and unjustified absences are not paid).
     *    They apply on scheduled workdays and also when the collaborator has
     *    no schedule, whose registered incidents are the only source of truth
     *    for the day.
     *  - Late arrivals: only discounted when the settings say so, and only
     *    when the late was not manually ignored.
     *  - Worked rest days: every worked minute is overtime, and the same
     *    minutes travel in the day payload, so the period detail reconciles
     *    with the weekly pool shown as the collaborator total.
     *
     * @return array{
     *     snapshot: array<string, mixed>,
     *     days: array<int, array<string, mixed>>,
     *     lines: array<int, array<string, mixed>>,
     *     totals: array<string, float|int>,
     * }
     */
    public function calculateFor(User $user, PayrollPeriod $period): array
    {
        $settings = PayrollSetting::current();
        $profile = $user->payrollProfile;

        $dailySalary = (float) ($profile?->daily_salary ?? 0);
        $dailyHours = (float) (($profile?->daily_hours ?? $settings->default_daily_hours) ?: 8);
        $dailyHours = $dailyHours > 0 ? $dailyHours : 8.0;
        $minuteRate = $dailyHours > 0 ? $dailySalary / ($dailyHours * 60) : 0.0;
        $lateDiscountEnabled = $settings->late_discount_mode === PayrollSetting::LATE_DEDUCT_MINUTES;

        $cursor = CarbonImmutable::parse($period->start_date->toDateString());
        $last = CarbonImmutable::parse($period->end_date->toDateString());
        $hiredOn = $profile?->hire_date ? CarbonImmutable::parse($profile->hire_date->toDateString()) : null;
        $terminatedOn = $profile?->termination_date ? CarbonImmutable::parse($profile->termination_date->toDateString()) : null;

        $days = [];
        $overtimePoolMinutes = 0.0;

        $totals = [
            'days_worked' => 0.0,
            'days_paid' => 0.0,
            'unpaid_days' => 0.0,
            'late_minutes' => 0,
            'late_discount' => 0.0,
            'overtime_double_minutes' => 0,
            'overtime_triple_minutes' => 0,
            'overtime_amount' => 0.0,
            'holiday_days' => 0.0,
            'holiday_amount' => 0.0,
            'vacation_days' => 0.0,
            'incapacity_days' => 0.0,
            'incapacity_amount' => 0.0,
            'adjustments_earnings' => 0.0,
            'adjustments_deductions' => 0.0,
        ];

        while ($cursor->lessThanOrEqualTo($last)) {
            // Days outside the employment window are not part of the payroll.
            if ($hiredOn !== null && $cursor->lessThan($hiredOn)) {
                $cursor = $cursor->addDay();

                continue;
            }

            if ($terminatedOn !== null && $cursor->greaterThan($terminatedOn)) {
                break;
            }

            $summary = $this->attendanceDayService->summaryFor($user, $cursor);
            $worked = $summary->workedMinutes;
            $dayFraction = 0.0;
            $countableWorkday = $summary->hasSchedule && $summary->isWorkday && $summary->holidayName === null;

            // Incidents pay on scheduled workdays and also when the collaborator
            // has no schedule at all: for those days the registered incident is
            // the only source of truth (e.g. vacations of collaborators without
            // an assigned shift). Holidays and the rest days of scheduled
            // collaborators keep their own rules.
            $incidentApplies = $summary->incidentTypeKey !== null
                && $summary->holidayName === null
                && ($countableWorkday || ! $summary->hasSchedule);

            if ($incidentApplies) {
                // 1. Incident day: paid fraction decided by the type/override.
                $fraction = $summary->incidentPayPercentage ?? 0.0;
                $dayFraction += $fraction;

                if ($fraction < 1.0) {
                    $totals['unpaid_days'] += 1.0 - $fraction;
                }

                if ($summary->incidentTypeKey === 'vacation') {
                    $totals['vacation_days'] += $fraction;
                } elseif ($summary->incidentTypeKey === 'medical_leave') {
                    $totals['incapacity_days'] += $fraction;
                    $totals['incapacity_amount'] += round($fraction * $dailySalary, 2);
                }
            } elseif ($summary->holidayName !== null) {
                // 2. Holiday: paid rest day; extra pay when worked.
                $dayFraction += 1.0;

                if ($summary->hasPunches() || $worked > 0) {
                    $totals['holiday_days'] += 1.0;
                    $totals['holiday_amount'] += round(
                        $dailySalary * (float) $settings->holiday_worked_extra_multiplier,
                        2
                    );
                }
            } elseif ($summary->hasSchedule && $summary->isWorkday) {
                // 3. Scheduled workday without incident.
                if ($summary->hasPunches() || $worked > 0) {
                    $dayFraction += 1.0;

                    $expected = max(1, $summary->expectedMinutes);
                    $totals['days_worked'] += min(1.0, $worked / $expected);

                    if ($lateDiscountEnabled) {
                        $totals['late_discount'] += round($summary->expectedLateMinutes() * $minuteRate, 2);
                    }

                    $totals['late_minutes'] += $summary->lateMinutes;
                    $overtimePoolMinutes += $summary->overtimeMinutes;
                } elseif ($summary->status === AttendanceDaySummary::STATUS_ABSENT) {
                    // The day already passed without a record: unjustified
                    // absence. Days that have not passed yet (today and
                    // future) stay pending and count neither as paid nor as
                    // unpaid.
                    $totals['unpaid_days'] += 1.0;
                }
            } elseif ($worked > 0) {
                // 4. Worked rest day (every minute is overtime, the same value
                // the day payload exposes) or day without an assigned schedule.
                if ($summary->hasSchedule) {
                    $overtimePoolMinutes += $summary->overtimeMinutes;
                } else {
                    $fraction = min(1.0, $worked / ($dailyHours * 60));
                    $dayFraction += $fraction;
                    $totals['days_worked'] += $fraction;
                }
            }

            $totals['days_paid'] += $dayFraction;

            $days[] = [
                'date' => $cursor->toDateString(),
                'status' => $summary->status,
                'status_label' => $summary->statusLabel(),
                'shift' => $summary->shift?->name,
                'expected_minutes' => $summary->expectedMinutes,
                'worked_minutes' => $worked,
                'late_minutes' => $summary->lateMinutes,
                'late_ignored' => $summary->lateIgnored,
                'late_effective_minutes' => $summary->expectedLateMinutes(),
                'overtime_minutes' => $summary->overtimeMinutes,
                'first_in' => $summary->firstIn?->format('H:i'),
                'lunch_start' => $summary->lunchStart?->format('H:i'),
                'lunch_end' => $summary->lunchEnd?->format('H:i'),
                'last_out' => $summary->lastOut?->format('H:i'),
                'incident_type' => $summary->incidentType,
                'incident_type_key' => $summary->incidentTypeKey,
                'holiday_name' => $summary->holidayName,
                'pay_fraction' => round($dayFraction, 4),
                'notes' => $summary->notes,
            ];

            $cursor = $cursor->addDay();
        }

        // Overtime split: double up to the weekly threshold, triple beyond.
        $thresholdMinutes = (float) $settings->overtime_weekly_threshold_hours * 60;
        $doubleMinutes = min($overtimePoolMinutes, $thresholdMinutes);
        $tripleMinutes = max(0.0, $overtimePoolMinutes - $thresholdMinutes);

        $doubleAmount = $doubleMinutes * $minuteRate * (float) $settings->overtime_double_multiplier;
        $tripleAmount = $tripleMinutes * $minuteRate * (float) $settings->overtime_triple_multiplier;

        $totals['overtime_double_minutes'] = (int) round($doubleMinutes);
        $totals['overtime_triple_minutes'] = (int) round($tripleMinutes);
        $totals['overtime_amount'] = round($doubleAmount + $tripleAmount, 2);

        // Manual adjustments.
        $adjustments = PayrollAdjustment::query()
            ->where('payroll_period_id', $period->id)
            ->where('user_id', $user->id)
            ->get();

        foreach ($adjustments as $adjustment) {
            if ($adjustment->type === PayrollAdjustment::TYPE_EARNING) {
                $totals['adjustments_earnings'] += (float) $adjustment->amount;
            } else {
                $totals['adjustments_deductions'] += (float) $adjustment->amount;
            }
        }

        $baseAmount = round($totals['days_paid'] * $dailySalary, 2);
        $totalGross = round(
            $baseAmount + $totals['overtime_amount'] + $totals['holiday_amount'] + $totals['adjustments_earnings'],
            2
        );
        $totalDeductions = round($totals['late_discount'] + $totals['adjustments_deductions'], 2);
        $totalNet = round($totalGross - $totalDeductions, 2);

        $totals['base_amount'] = $baseAmount;
        $totals['total_gross'] = $totalGross;
        $totals['total_deductions'] = $totalDeductions;
        $totals['total_net'] = $totalNet;

        return [
            'snapshot' => [
                'employee_number' => $profile?->employee_number,
                'department' => $user->employee?->department,
                'position' => $user->employee?->position,
                'hire_date' => $profile?->hire_date?->toDateString(),
                'termination_date' => $profile?->termination_date?->toDateString(),
                'daily_salary' => $dailySalary,
                'daily_hours' => $dailyHours,
            ],
            'days' => $days,
            'lines' => $this->buildLines($totals, $dailySalary, $minuteRate, $doubleAmount, $tripleAmount, $adjustments, $settings),
            'totals' => $totals,
        ];
    }

    /**
     * @param  Collection<int, PayrollAdjustment>  $adjustments
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(
        array $totals,
        float $dailySalary,
        float $minuteRate,
        float $doubleAmount,
        float $tripleAmount,
        Collection $adjustments,
        PayrollSetting $settings,
    ): array {
        $lines = [];
        $sort = 0;

        $lines[] = [
            'concept' => 'Sueldo',
            'type' => 'earning',
            'quantity' => round($totals['days_paid'], 2),
            'unit_rate' => round($dailySalary, 2),
            'amount' => round($totals['days_paid'] * $dailySalary, 2),
            'source' => 'attendance',
            'sort_order' => $sort += 10,
        ];

        if ($totals['overtime_double_minutes'] > 0) {
            $lines[] = [
                'concept' => 'Tiempo extra doble',
                'type' => 'earning',
                'quantity' => round($totals['overtime_double_minutes'] / 60, 2),
                'unit_rate' => round($minuteRate * 60 * (float) $settings->overtime_double_multiplier, 2),
                'amount' => round($doubleAmount, 2),
                'source' => 'overtime',
                'sort_order' => $sort += 10,
            ];
        }

        if ($totals['overtime_triple_minutes'] > 0) {
            $lines[] = [
                'concept' => 'Tiempo extra triple',
                'type' => 'earning',
                'quantity' => round($totals['overtime_triple_minutes'] / 60, 2),
                'unit_rate' => round($minuteRate * 60 * (float) $settings->overtime_triple_multiplier, 2),
                'amount' => round($tripleAmount, 2),
                'source' => 'overtime',
                'sort_order' => $sort += 10,
            ];
        }

        if ($totals['holiday_amount'] > 0) {
            $lines[] = [
                'concept' => 'Día festivo laborado',
                'type' => 'earning',
                'quantity' => round($totals['holiday_days'], 2),
                'unit_rate' => round($dailySalary * (float) $settings->holiday_worked_extra_multiplier, 2),
                'amount' => round($totals['holiday_amount'], 2),
                'source' => 'holiday',
                'sort_order' => $sort += 10,
            ];
        }

        foreach ($adjustments as $adjustment) {
            if ($adjustment->type !== PayrollAdjustment::TYPE_EARNING) {
                continue;
            }

            $lines[] = [
                'concept' => $adjustment->concept,
                'type' => 'earning',
                'quantity' => null,
                'unit_rate' => null,
                'amount' => round((float) $adjustment->amount, 2),
                'source' => 'adjustment',
                'sort_order' => $sort += 10,
            ];
        }

        $deductionSort = 100;

        if ($totals['late_discount'] > 0) {
            $lines[] = [
                'concept' => 'Retardos',
                'type' => 'deduction',
                'quantity' => null,
                'unit_rate' => null,
                'amount' => round($totals['late_discount'], 2),
                'source' => 'attendance',
                'sort_order' => $deductionSort += 10,
            ];
        }

        foreach ($adjustments as $adjustment) {
            if ($adjustment->type !== PayrollAdjustment::TYPE_DEDUCTION) {
                continue;
            }

            $lines[] = [
                'concept' => $adjustment->concept,
                'type' => 'deduction',
                'quantity' => null,
                'unit_rate' => null,
                'amount' => round((float) $adjustment->amount, 2),
                'source' => 'adjustment',
                'sort_order' => $deductionSort += 10,
            ];
        }

        return $lines;
    }
}
