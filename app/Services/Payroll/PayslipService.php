<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Collection;

class PayslipService
{
    public function __construct(
        private readonly PayrollCalculatorService $calculator,
    ) {}

    /**
     * Freeze the pre-payroll of every payroll subject into payslips.
     *
     * @return Collection<int, Payslip>
     */
    public function generateFor(PayrollPeriod $period, ?User $actor = null): Collection
    {
        $this->deleteFor($period);

        $payslips = collect();

        foreach ($this->calculator->payrollSubjects($period->start_date) as $user) {
            $result = $this->calculator->calculateFor($user, $period);

            $payslips->push($this->store($user, $period, $result, $actor));
        }

        return $payslips;
    }

    public function deleteFor(PayrollPeriod $period): void
    {
        $period->payslips()->get()->each->delete();
    }

    /**
     * Printable payload of a period. Closed periods use the frozen payslips;
     * open periods are calculated live so receipts can be printed before the
     * period is closed (the pre-payroll is the same data shown on screen).
     *
     * @param  array<int, int>  $userIds  Empty means every collaborator of the period.
     * @return Collection<int, array<string, mixed>>
     */
    public function printPayloads(PayrollPeriod $period, array $userIds = []): Collection
    {
        return $this->payloads($period, $userIds, false);
    }

    /**
     * Same payload plus the day-by-day detail, used by the pre-payroll sheet of
     * the whole period.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    public function prePayrollPayloads(PayrollPeriod $period, array $userIds = []): Collection
    {
        return $this->payloads($period, $userIds, true);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function payloads(PayrollPeriod $period, array $userIds, bool $withDays): Collection
    {
        if ($period->isOpen()) {
            return $this->calculator->payrollSubjects($period->start_date)
                ->filter(fn (User $user) => $userIds === [] || in_array($user->id, $userIds, true))
                ->map(fn (User $user) => $this->mapPreview($user, $this->calculator->calculateFor($user, $period), $withDays))
                ->values();
        }

        return $period->payslips()
            ->with(['user:id,name', 'user.payrollProfile', 'lines', 'days'])
            ->when($userIds !== [], fn ($query) => $query->whereIn('user_id', $userIds))
            ->get()
            ->map(fn (Payslip $payslip) => $this->mapPayslip($payslip, $withDays))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPayslip(Payslip $payslip, bool $withDays): array
    {
        return [
            'id' => $payslip->id,
            'user_id' => $payslip->user_id,
            'user_name' => $payslip->user?->name,
            'employee_number' => $payslip->employee_number,
            'department' => $payslip->department,
            'position' => $payslip->position,
            'termination_date' => $payslip->user?->payrollProfile?->termination_date?->toDateString(),
            'days_worked' => (float) $payslip->days_worked,
            'days_paid' => (float) $payslip->days_paid,
            'unpaid_days' => (float) $payslip->unpaid_days,
            'late_minutes' => (int) $payslip->late_minutes,
            'overtime_minutes' => (int) $payslip->overtime_double_minutes + (int) $payslip->overtime_triple_minutes,
            'vacation_days' => (float) $payslip->vacation_days,
            'incapacity_days' => (float) $payslip->incapacity_days,
            'adjustments_earnings' => (float) $payslip->adjustments_earnings,
            'adjustments_deductions' => (float) $payslip->adjustments_deductions,
            'daily_salary' => (float) $payslip->daily_salary,
            'total_gross' => (float) $payslip->total_gross,
            'total_deductions' => (float) $payslip->total_deductions,
            'total_net' => (float) $payslip->total_net,
            'days' => $withDays
                ? $payslip->days->map(fn ($day) => [
                    'date' => $day->date->toDateString(),
                    'status' => $day->status,
                    'status_label' => null,
                    'worked_minutes' => $day->worked_minutes,
                    'late_minutes' => $day->late_minutes,
                    'overtime_minutes' => $day->overtime_minutes,
                    'first_in' => $day->first_in,
                    'lunch_start' => $day->lunch_start,
                    'lunch_end' => $day->lunch_end,
                    'last_out' => $day->last_out,
                    'notes' => $day->notes,
                ])->values()
                : [],
            'lines' => $payslip->lines->map(fn ($line) => [
                'concept' => $line->concept,
                'type' => $line->type,
                'quantity' => $line->quantity !== null ? (float) $line->quantity : null,
                'amount' => (float) $line->amount,
            ])->values(),
        ];
    }

    /**
     * Same shape as mapPayslip(), but built from the live calculation.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mapPreview(User $user, array $result, bool $withDays): array
    {
        $totals = $result['totals'];
        $snapshot = $result['snapshot'];

        return [
            'id' => null,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'employee_number' => $snapshot['employee_number'],
            'department' => $snapshot['department'],
            'position' => $snapshot['position'],
            'termination_date' => $snapshot['termination_date'] ?? null,
            'days_worked' => (float) $totals['days_worked'],
            'days_paid' => (float) $totals['days_paid'],
            'unpaid_days' => (float) $totals['unpaid_days'],
            'late_minutes' => (int) $totals['late_minutes'],
            'overtime_minutes' => (int) $totals['overtime_double_minutes'] + (int) $totals['overtime_triple_minutes'],
            'vacation_days' => (float) $totals['vacation_days'],
            'incapacity_days' => (float) $totals['incapacity_days'],
            'adjustments_earnings' => (float) $totals['adjustments_earnings'],
            'adjustments_deductions' => (float) $totals['adjustments_deductions'],
            'daily_salary' => (float) $snapshot['daily_salary'],
            'total_gross' => (float) $totals['total_gross'],
            'total_deductions' => (float) $totals['total_deductions'],
            'total_net' => (float) $totals['total_net'],
            'days' => $withDays ? collect($result['days'])->values() : [],
            'lines' => collect($result['lines'])->map(fn (array $line) => [
                'concept' => $line['concept'],
                'type' => $line['type'],
                'quantity' => $line['quantity'] !== null ? (float) $line['quantity'] : null,
                'amount' => (float) $line['amount'],
            ])->values(),
        ];
    }

    private function store(User $user, PayrollPeriod $period, array $result, ?User $actor): Payslip
    {
        $totals = $result['totals'];
        $snapshot = $result['snapshot'];

        $payslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'user_id' => $user->id,
            'employee_number' => $snapshot['employee_number'],
            'department' => $snapshot['department'],
            'position' => $snapshot['position'],
            'hire_date' => $snapshot['hire_date'],
            'daily_salary' => $snapshot['daily_salary'],
            'daily_hours' => $snapshot['daily_hours'],
            'days_worked' => $totals['days_worked'],
            'days_paid' => $totals['days_paid'],
            'unpaid_days' => $totals['unpaid_days'],
            'late_minutes' => $totals['late_minutes'],
            'late_discount' => $totals['late_discount'],
            'overtime_double_minutes' => $totals['overtime_double_minutes'],
            'overtime_triple_minutes' => $totals['overtime_triple_minutes'],
            'overtime_amount' => $totals['overtime_amount'],
            'holiday_days' => $totals['holiday_days'],
            'holiday_amount' => $totals['holiday_amount'],
            'vacation_days' => $totals['vacation_days'],
            'incapacity_days' => $totals['incapacity_days'],
            'incapacity_amount' => $totals['incapacity_amount'],
            'adjustments_earnings' => $totals['adjustments_earnings'],
            'adjustments_deductions' => $totals['adjustments_deductions'],
            'total_gross' => $totals['total_gross'],
            'total_deductions' => $totals['total_deductions'],
            'total_net' => $totals['total_net'],
            'generated_at' => now(),
            'generated_by' => $actor?->id,
        ]);

        foreach ($result['lines'] as $line) {
            $payslip->lines()->create($line);
        }

        foreach ($result['days'] as $day) {
            $payslip->days()->create([
                'date' => $day['date'],
                'status' => $day['status'],
                'first_in' => $day['first_in'],
                'lunch_start' => $day['lunch_start'],
                'lunch_end' => $day['lunch_end'],
                'last_out' => $day['last_out'],
                'worked_minutes' => $day['worked_minutes'],
                'late_minutes' => $day['late_minutes'],
                'overtime_minutes' => $day['overtime_minutes'],
                'notes' => $day['notes'],
            ]);
        }

        return $payslip;
    }
}
