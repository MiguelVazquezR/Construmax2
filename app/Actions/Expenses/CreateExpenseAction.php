<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Services\Expenses\ExpenseReceiptService;

class CreateExpenseAction
{
    public function __construct(
        private readonly ExpenseReceiptService $receiptService,
    ) {}

    /**
     * Register a general expense or an extra expense linked to a budget.
     */
    public function execute(array $data): Expense
    {
        $isCommission = ! empty($data['budget_id']) && (bool) ($data['is_commission'] ?? false);

        $expense = Expense::create([
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'budget_id' => $data['budget_id'] ?? null,
            'payroll_period_id' => $data['payroll_period_id'] ?? null,
            'is_commission' => $isCommission,
            'concept' => $data['concept'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'amount' => $data['amount'],
            // A general commission expense does not carry another commission on top.
            'commission_amount' => $isCommission ? null : ($data['commission_amount'] ?? null),
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'],
            'paid_at' => $data['status'] === Expense::STATUS_PAID ? now() : null,
            // System-generated expenses (payroll close) have no acting user.
            'created_by' => array_key_exists('created_by', $data) ? $data['created_by'] : auth()->id(),
        ]);

        if (! empty($data['receipts'])) {
            $this->receiptService->attachMany($expense, $data['receipts']);
        }

        return $expense;
    }
}
