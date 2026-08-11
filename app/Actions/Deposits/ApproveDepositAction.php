<?php

namespace App\Actions\Deposits;

use App\Models\Deposit;
use App\Models\User;

class ApproveDepositAction
{
    public function execute(Deposit $deposit, User $approver): Deposit
    {
        $deposit->update([
            'status'      => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $deposit;
    }
}
