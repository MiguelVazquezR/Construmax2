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
     * Monday–Sunday range of the work week that contains the given date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function weekRange(?CarbonImmutable $date = null): array
    {
        $date = $date ?? CarbonImmutable::today();
        $monday = $date->startOfWeek(CarbonImmutable::MONDAY);

        return [$monday, $monday->addDays(6)];
    }

    /**
     * Dates of the next period that can be opened: the first full Monday–Sunday
     * week after the latest period that does not repeat existing dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function nextPeriodRange(): array
    {
        $lastPeriod = PayrollPeriod::query()->orderByDesc('start_date')->first();

        if (! $lastPeriod) {
            return $this->weekRange();
        }

        $after = CarbonImmutable::parse($lastPeriod->end_date->toDateString())->addDay();
        $monday = $after->startOfWeek(CarbonImmutable::MONDAY);

        if ($monday->lessThan($after)) {
            $monday = $monday->addWeek();
        }

        while ($this->rangeConflicts($monday, $monday->addDays(6))) {
            $monday = $monday->addWeek();
        }

        return [$monday, $monday->addDays(6)];
    }

    /**
     * Open the next period (the "Abrir siguiente periodo" action).
     */
    public function openNextPeriod(): PayrollPeriod
    {
        [$start, $end] = $this->nextPeriodRange();

        return $this->createPeriod($start, $end);
    }

    /**
     * Close the Monday–Sunday week that just ended. Periods reopened by hand
     * stay open: the manager keeps them that way to fix their figures.
     */
    public function closeEndedWeek(?CarbonImmutable $now = null): ?PayrollPeriod
    {
        $now = $now ?? CarbonImmutable::now();
        $lastSunday = $now->isSunday() && $now->format('H:i') === '23:59'
            ? $now
            : $now->startOfWeek(CarbonImmutable::MONDAY)->subDay();

        $period = PayrollPeriod::query()
            ->open()
            ->whereDate('end_date', $lastSunday->toDateString())
            ->whereNull('reopened_at')
            ->orderBy('start_date')
            ->first();

        return $period ? $this->close($period) : null;
    }

    /**
     * Open the current week (Monday–Sunday) once it has started, unless a
     * period with those exact dates already exists.
     */
    public function ensureCurrentWeek(?CarbonImmutable $now = null): ?PayrollPeriod
    {
        [$monday, $sunday] = $this->weekRange($now);

        if ($this->rangeConflicts($monday, $sunday)) {
            return null;
        }

        return $this->createPeriod($monday, $sunday);
    }

    /**
     * Automatic weekly rollover: closes the week that just ended (Sunday
     * 23:59) and opens the current Monday–Sunday week (Monday 00:00).
     *
     * @return array{closed: ?PayrollPeriod, opened: ?PayrollPeriod}
     */
    public function syncAutomatic(?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();

        return [
            'closed' => $this->closeEndedWeek($now),
            'opened' => $this->ensureCurrentWeek($now),
        ];
    }

    private function createPeriod(CarbonImmutable $start, CarbonImmutable $end): PayrollPeriod
    {
        if ($this->rangeConflicts($start, $end)) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe un periodo de nómina con esas fechas.',
            ]);
        }

        return PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * True when the range shares days with an open period or repeats the exact
     * dates of any existing one: two periods can never cover the same week.
     */
    private function rangeConflicts(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return PayrollPeriod::query()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($open) use ($startDate, $endDate) {
                    $open->where('status', PayrollPeriod::STATUS_OPEN)
                        ->whereDate('start_date', '<=', $endDate)
                        ->whereDate('end_date', '>=', $startDate);
                })->orWhere(function ($same) use ($startDate, $endDate) {
                    $same->whereDate('start_date', $startDate)
                        ->whereDate('end_date', $endDate);
                });
            })
            ->exists();
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
                'reopened_at' => now(),
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
}
