<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Services\Expenses\ExpenseReceiptService;
use Illuminate\Support\Carbon;

class UpdateExpenseAction
{
    public function __construct(
        private readonly ExpenseReceiptService $receiptService,
    ) {}

    public function execute(Expense $expense, array $data): Expense
    {
        $expense->update([
            'expense_category_id' => $data['expense_category_id'],
            'ticket_id' => $data['ticket_id'] ?? null,
            'concept' => $data['concept'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'],
            'paid_at' => $this->resolvePaidAt($expense, $data['status']),
        ]);

        if (isset($data['receipt'])) {
            $this->receiptService->attach($expense, $data['receipt']);
        } elseif ($data['remove_receipt'] ?? false) {
            $this->receiptService->remove($expense);
        }

        return $expense;
    }

    /**
     * Keep paid_at in sync with the status: first time it is paid the date is
     * stamped, and it is cleared when the expense stops being paid.
     */
    private function resolvePaidAt(Expense $expense, string $status): ?Carbon
    {
        if ($status === Expense::STATUS_PAID) {
            return $expense->paid_at ?? now();
        }

        return null;
    }
}
