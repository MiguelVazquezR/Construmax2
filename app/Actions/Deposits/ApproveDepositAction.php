<?php

namespace App\Actions\Deposits;

use App\Actions\Expenses\SyncDepositExpenseAction;
use App\Models\Deposit;
use App\Models\User;

class ApproveDepositAction
{
    public function __construct(
        private readonly SyncDepositExpenseAction $syncDepositExpenseAction,
    ) {}

    public function execute(Deposit $deposit, User $approver): Deposit
    {
        $deposit->update([
            'status'      => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        // Approved deposits are mirrored as pending expenses in the expenses module.
        $this->syncDepositExpenseAction->execute($deposit->fresh());

        return $deposit;
    }
}
