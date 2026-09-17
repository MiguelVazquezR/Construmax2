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
     * Register a general or ticket-linked expense.
     */
    public function execute(array $data): Expense
    {
        $expense = Expense::create([
            'expense_category_id' => $data['expense_category_id'],
            'ticket_id' => $data['ticket_id'] ?? null,
            'concept' => $data['concept'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'],
            'paid_at' => $data['status'] === Expense::STATUS_PAID ? now() : null,
            'created_by' => auth()->id(),
        ]);

        if (isset($data['receipt'])) {
            $this->receiptService->attach($expense, $data['receipt']);
        }

        return $expense;
    }
}
