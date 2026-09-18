<?php

namespace App\Actions\Expenses;

use App\Models\Budget;
use App\Models\Expense;

class MarkBudgetConceptsPaidAction
{
    public function __construct(
        private readonly RegisterBudgetConceptPaymentAction $registerPaymentAction,
        private readonly CreateExpenseAction $createExpenseAction,
    ) {}

    /**
     * Register the payment of several breakdown concepts at once. When a
     * commission is provided (e.g. the OXXO fee of the whole movement) it is
     * registered once as a general commission of the budget.
     *
     * @param array<string, mixed> $data
     * @return int Number of concepts paid
     */
    public function execute(Budget $budget, array $data): int
    {
        $concepts = $budget->concepts()
            ->whereIn('id', $data['concepts'])
            ->get();

        foreach ($concepts as $concept) {
            $this->registerPaymentAction->execute($concept, [
                'status' => Expense::STATUS_PAID,
                'payment_date' => $data['payment_date'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
            ]);
        }

        $commission = (float) ($data['commission_amount'] ?? 0);

        if ($commission > 0) {
            $this->createExpenseAction->execute([
                'budget_id' => $budget->id,
                'is_commission' => true,
                'concept' => 'Comisión de pago',
                'amount' => $commission,
                'expense_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Expense::STATUS_PAID,
            ]);
        }

        return $concepts->count();
    }
}
