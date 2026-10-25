<?php

namespace App\Actions\Expenses;

use App\Models\BudgetConcept;
use App\Models\Expense;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegisterBulkConceptPaymentsAction
{
    public const COMMISSION_MODE_NONE = 'none';

    public const COMMISSION_MODE_FIXED = 'fixed';

    public const COMMISSION_MODE_PERCENT = 'percent';

    public function __construct(
        private readonly RegisterBudgetConceptPaymentAction $registerPaymentAction,
    ) {}

    /**
     * Pay several breakdown concepts (from one or more budgets) sharing the
     * same date and payment method. The commission is distributed among the
     * concepts being paid: evenly for a fixed total, proportionally for a
     * percentage.
     *
     * @param array<string, mixed> $data
     * @return int Number of concepts paid
     */
    public function execute(array $data): int
    {
        $concepts = BudgetConcept::query()
            ->with('expense')
            ->whereIn('id', $data['concepts'])
            ->whereDoesntHave('expense', fn ($query) => $query->where('status', Expense::STATUS_PAID))
            ->orderBy('id')
            ->get();

        if ($concepts->isEmpty()) {
            return 0;
        }

        $commissions = $this->distributeCommission(
            $concepts,
            $data['commission_mode'] ?? self::COMMISSION_MODE_NONE,
            (float) ($data['commission_value'] ?? 0),
        );

        DB::transaction(function () use ($concepts, $commissions, $data) {
            foreach ($concepts as $concept) {
                $this->registerPaymentAction->execute($concept, [
                    'status' => Expense::STATUS_PAID,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'commission_amount' => $commissions[$concept->id] ?? null,
                ]);
            }
        });

        return $concepts->count();
    }

    /**
     * @param Collection<int, BudgetConcept> $concepts
     * @return array<int, float> Commission keyed by concept id
     */
    private function distributeCommission(Collection $concepts, string $mode, float $value): array
    {
        if ($mode === self::COMMISSION_MODE_NONE || $value <= 0) {
            return [];
        }

        if ($mode === self::COMMISSION_MODE_PERCENT) {
            return $concepts
                ->mapWithKeys(fn (BudgetConcept $concept) => [
                    $concept->id => round((float) $concept->amount * $value / 100, 2),
                ])
                ->filter(fn (float $commission) => $commission > 0)
                ->all();
        }

        $count = $concepts->count();
        $share = round($value / $count, 2);
        $remaining = round($value, 2);
        $commissions = [];

        foreach ($concepts->values() as $index => $concept) {
            // The last concept absorbs the rounding difference so the split adds up.
            $commission = $index === $count - 1 ? round($remaining, 2) : $share;

            if ($commission > 0) {
                $commissions[$concept->id] = $commission;
            }

            $remaining -= $commission;
        }

        return $commissions;
    }
}
