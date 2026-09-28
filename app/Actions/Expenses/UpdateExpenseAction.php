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
        // Deposit expenses mirror a deposit: money fields and receipts are
        // managed from the deposit completion flow ("Marcar realizado").
        if ($expense->deposit_id) {
            $data['concept'] = $expense->concept;
            $data['amount'] = $expense->amount;
            $data['budget_id'] = $expense->budget_id;
            $data['expense_date'] = $expense->expense_date?->format('Y-m-d');
            $data['status'] = $expense->status;
            $data['payment_method'] = $expense->payment_method;
            $data['commission_amount'] = $expense->commission_amount;
            $data['is_commission'] = false;

            unset($data['receipts'], $data['remove_receipt_ids']);
        }

        // Payroll expenses mirror a closed payroll period: only their payment
        // data (status, payment method, notes) can be edited.
        if ($expense->payroll_period_id) {
            $data['concept'] = $expense->concept;
            $data['amount'] = $expense->amount;
            $data['budget_id'] = $expense->budget_id;
            $data['expense_date'] = $expense->expense_date?->format('Y-m-d');
            $data['commission_amount'] = $expense->commission_amount;
            $data['is_commission'] = false;

            unset($data['receipts'], $data['remove_receipt_ids']);
        }

        // Concept payments keep the concept and amount defined by the budget
        // breakdown; only their payment data can be edited.
        if ($expense->budget_concept_id) {
            $concept = $expense->loadMissing('budgetConcept')->budgetConcept;

            $data['concept'] = $concept->concept;
            $data['amount'] = $concept->amount;
            $data['budget_id'] = $concept->budget_id;
            $data['is_commission'] = false;
        }

        $isCommission = ! empty($expense->budget_id) && (bool) ($data['is_commission'] ?? false);

        $expense->update([
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'budget_id' => array_key_exists('budget_id', $data) ? $data['budget_id'] : $expense->budget_id,
            'is_commission' => $isCommission,
            'concept' => $data['concept'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'amount' => $data['amount'],
            'commission_amount' => $isCommission
                ? null
                : (array_key_exists('commission_amount', $data) ? $data['commission_amount'] : $expense->commission_amount),
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'],
            'paid_at' => $this->resolvePaidAt($expense, $data['status']),
        ]);

        if (! empty($data['receipts'])) {
            $this->receiptService->attachMany($expense, $data['receipts']);
        }

        if (! empty($data['remove_receipt_ids'])) {
            $this->receiptService->removeMany($expense, $data['remove_receipt_ids']);
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
