<?php

namespace App\Services\Payroll;

use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Notifications\DispatchNotificationAction;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollPeriodService
{
    public function __construct(
        private readonly PayslipService $payslipService,
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly DispatchNotificationAction $dispatchNotificationAction,
    ) {}

    public function currentOpenPeriod(): ?PayrollPeriod
    {
        return PayrollPeriod::query()->open()->orderBy('start_date')->first();
    }

    /**
     * Open period that already ended and is due for automatic closing.
     */
    public function duePeriod(?CarbonImmutable $today = null): ?PayrollPeriod
    {
        $today = $today ?? CarbonImmutable::today();

        return PayrollPeriod::query()
            ->open()
            ->whereDate('end_date', '<', $today->toDateString())
            ->orderBy('start_date')
            ->first();
    }

    /**
     * Create the first period containing today, based on the configured type
     * and anchor date. No-op when an open period already exists.
     */
    public function createFirstPeriod(): ?PayrollPeriod
    {
        if ($existing = $this->currentOpenPeriod()) {
            return $existing;
        }

        $settings = PayrollSetting::current();
        $type = $settings->period_type;
        $today = CarbonImmutable::today();

        if ($type === PayrollSetting::PERIOD_SEMIMONTHLY) {
            $start = $today->day <= 15
                ? $today->startOfMonth()
                : $today->startOfMonth()->addDays(15);
        } else {
            $periodDays = $type === PayrollSetting::PERIOD_BIWEEKLY ? 14 : 7;
            $anchor = $settings->period_anchor_date
                ? CarbonImmutable::parse($settings->period_anchor_date->toDateString())
                : $today;

            if ($anchor->greaterThan($today)) {
                $anchor = $today;
            }

            $elapsed = (int) $anchor->diffInDays($today);
            $periods = intdiv($elapsed, $periodDays);
            $start = $anchor->addDays($periods * $periodDays);
        }

        return PayrollPeriod::create([
            'type' => $type,
            'start_date' => $start->toDateString(),
            'end_date' => $this->endDateFor($type, $start)->toDateString(),
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * Create the period that follows a closed one (idempotent).
     */
    public function createNextPeriod(PayrollPeriod $period): PayrollPeriod
    {
        $start = $this->nextStartDate($period);

        $existing = PayrollPeriod::query()
            ->whereDate('start_date', $start->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return PayrollPeriod::create([
            'type' => $period->type,
            'start_date' => $start->toDateString(),
            'end_date' => $this->endDateFor($period->type, $start)->toDateString(),
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * Close a period: freeze payslips, store totals, register the payroll
     * expense in "Control de gastos" and email the subscribers.
     */
    public function close(PayrollPeriod $period, ?User $actor = null): PayrollPeriod
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'El periodo ya está cerrado.',
            ]);
        }

        return DB::transaction(function () use ($period, $actor) {
            $payslips = $this->payslipService->generateFor($period, $actor);

            $gross = round($payslips->sum(fn ($payslip) => (float) $payslip->total_gross), 2);
            $deductions = round($payslips->sum(fn ($payslip) => (float) $payslip->total_deductions), 2);
            $net = round($payslips->sum(fn ($payslip) => (float) $payslip->total_net), 2);

            $expense = $this->registerExpense($period, $net, $payslips->count(), $actor);

            $period->update([
                'status' => PayrollPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $actor?->id,
                'total_gross' => $gross,
                'total_deductions' => $deductions,
                'total_net' => $net,
                'expense_id' => $expense?->id,
            ]);

            $this->dispatchNotificationAction->payrollPeriodClosed($period->fresh(), $payslips->count());

            return $period->fresh();
        });
    }

    /**
     * Reopen a closed period: removes the frozen payslips and the mirror
     * expense (unless it was already paid).
     */
    public function reopen(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        if ($period->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'El periodo ya está abierto.',
            ]);
        }

        $expense = $period->expense;

        if ($expense && $expense->status === Expense::STATUS_PAID) {
            throw ValidationException::withMessages([
                'status' => 'El gasto del periodo ya fue pagado: no es posible reabrirlo.',
            ]);
        }

        return DB::transaction(function () use ($period, $expense) {
            $expense?->delete();
            $this->payslipService->deleteFor($period);

            $period->update([
                'status' => PayrollPeriod::STATUS_OPEN,
                'closed_at' => null,
                'closed_by' => null,
                'total_gross' => null,
                'total_deductions' => null,
                'total_net' => null,
                'expense_id' => null,
            ]);

            return $period->fresh();
        });
    }

    private function registerExpense(PayrollPeriod $period, float $net, int $count, ?User $actor): ?Expense
    {
        if ($net <= 0) {
            return null;
        }

        $settings = PayrollSetting::current();

        return $this->createExpenseAction->execute([
            'concept' => 'Nómina del periodo '.$period->label(),
            'reference' => 'Nómina #'.$period->id,
            'notes' => "Nómina de {$count} colaborador(es). Generado automáticamente al cerrar el periodo.",
            'amount' => $net,
            'expense_date' => $period->end_date->toDateString(),
            'payment_method' => 'transfer',
            'status' => Expense::STATUS_PENDING,
            'expense_category_id' => $settings->payroll_expense_category_id,
            'payroll_period_id' => $period->id,
            'created_by' => $actor?->id,
        ]);
    }

    private function nextStartDate(PayrollPeriod $period): CarbonImmutable
    {
        if ($period->type === PayrollSetting::PERIOD_SEMIMONTHLY) {
            $start = CarbonImmutable::parse($period->start_date->toDateString());

            return $start->day <= 15
                ? $start->startOfMonth()->addDays(15) // 16th
                : $start->startOfMonth()->addMonth(); // 1st of next month
        }

        $end = CarbonImmutable::parse($period->end_date->toDateString());

        return $end->addDay();
    }

    private function endDateFor(string $type, CarbonImmutable $start): CarbonImmutable
    {
        return match ($type) {
            PayrollSetting::PERIOD_BIWEEKLY => $start->addDays(13),
            PayrollSetting::PERIOD_SEMIMONTHLY => $start->day <= 15
                ? $start->startOfMonth()->addDays(14)
                : $start->endOfMonth(),
            default => $start->addDays(6),
        };
    }
}
