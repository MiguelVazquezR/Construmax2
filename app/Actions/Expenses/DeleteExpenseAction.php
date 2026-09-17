<?php

namespace App\Actions\Expenses;

use App\Models\Expense;

class DeleteExpenseAction
{
    public function execute(Expense $expense): void
    {
        $expense->delete();
    }
}
