<?php

namespace App\Actions\Expenses;

use App\Models\BudgetConcept;
use App\Models\Expense;
use App\Services\Expenses\ExpenseReceiptService;

class RegisterBudgetConceptPaymentAction
{
    public function __construct(
        private readonly ExpenseReceiptService $receiptService,
    ) {}

    /**
     * Create or update the expense that pays a budget breakdown concept.
     *
     * @param array<string, mixed> $data
     */
    public function execute(BudgetConcept $concept, array $data): Expense
    {
        $status = $data['status'] ?? Expense::STATUS_PAID;

        $expense = Expense::firstOrNew(['budget_concept_id' => $concept->id]);

        $expense->fill([
            'expense_category_id' => $data['expense_category_id'] ?? $expense->expense_category_id,
            'budget_id' => $concept->budget_id,
            'concept' => $concept->concept,
            'amount' => $concept->amount,
            'commission_amount' => array_key_exists('commission_amount', $data)
                ? $data['commission_amount']
                : $expense->commission_amount,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'expense_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $status,
            'is_commission' => false,
        ]);

        if (!$expense->exists) {
            $expense->created_by = auth()->id();
        }

        $expense->paid_at = $status === Expense::STATUS_PAID ? ($expense->paid_at ?? now()) : null;
        $expense->save();

        // Keep the payment date shown on the budget breakdown in sync.
        $concept->update([
            'payment_date' => $status === Expense::STATUS_PAID ? $data['payment_date'] : null,
        ]);

        if (!empty($data['receipts'])) {
            $this->receiptService->attachMany($expense, $data['receipts']);
        }

        if (!empty($data['remove_receipt_ids'])) {
            $this->receiptService->removeMany($expense, $data['remove_receipt_ids']);
        }

        return $expense;
    }
}
