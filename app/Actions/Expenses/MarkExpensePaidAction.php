<?php

namespace App\Actions\Expenses;

use App\Models\Expense;

class MarkExpensePaidAction
{
    /**
     * Mark a pending expense as paid.
     */
    public function execute(Expense $expense): Expense
    {
        $expense->update([
            'status' => Expense::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $expense;
    }
}
