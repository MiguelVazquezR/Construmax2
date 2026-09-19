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

        foreach ($this->calculator->payrollSubjects() as $user) {
            $result = $this->calculator->calculateFor($user, $period);

            $payslips->push($this->store($user, $period, $result, $actor));
        }

        return $payslips;
    }

    public function deleteFor(PayrollPeriod $period): void
    {
        $period->payslips()->get()->each->delete();
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
