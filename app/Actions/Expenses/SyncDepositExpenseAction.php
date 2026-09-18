<?php

namespace App\Actions\Expenses;

use App\Models\Deposit;
use App\Models\Expense;

class SyncDepositExpenseAction
{
    /**
     * Create or update the expense that mirrors a deposit.
     *
     * Pending deposits are not mirrored: the expense appears once the deposit
     * is approved. The deposit stays the source of truth: amount, commission,
     * status and concept are reflected here, and the voucher is copied into
     * the expense receipts the first time it is available.
     */
    public function execute(Deposit $deposit): ?Expense
    {
        if (!in_array($deposit->status, ['approved', 'completed'], true)) {
            return null;
        }

        $deposit->loadMissing(['depositType', 'technician.user']);

        $expense = Expense::firstOrNew(['deposit_id' => $deposit->id]);

        $attributes = [
            'budget_id' => $deposit->budget_id,
            'concept' => $this->conceptFor($deposit),
            'amount' => $deposit->amount,
            'commission_amount' => $deposit->commission_amount,
            'expense_date' => $this->dateFor($deposit),
            'payment_method' => 'transfer',
            'status' => $deposit->status === 'completed' ? Expense::STATUS_PAID : Expense::STATUS_PENDING,
            'is_commission' => false,
        ];

        if (!$expense->exists) {
            $attributes['reference'] = "Depósito #{$deposit->id}";
            $attributes['notes'] = $deposit->notes;
            $attributes['created_by'] = $deposit->created_by;
        }

        $expense->fill($attributes);

        $expense->paid_at = $deposit->status === 'completed'
            ? ($deposit->completed_at ?? now())
            : null;

        $expense->save();

        $this->copyVoucherToExpense($deposit, $expense);

        return $expense;
    }

    /**
     * Human readable concept built from the deposit type and beneficiary.
     */
    private function conceptFor(Deposit $deposit): string
    {
        $concept = $deposit->is_external ? 'Depósito externo' : 'Depósito';

        if ($deposit->depositType?->name) {
            $concept .= ': ' . $deposit->depositType->name;
        }

        $party = $deposit->is_external
            ? $deposit->external_beneficiary_name
            : $deposit->technician?->user?->name;

        if ($party) {
            $concept .= ' — ' . $party;
        }

        return $concept;
    }

    private function dateFor(Deposit $deposit): string
    {
        if ($deposit->status === 'completed' && $deposit->completed_at) {
            return $deposit->completed_at->toDateString();
        }

        return $deposit->scheduled_date?->toDateString() ?? now()->toDateString();
    }

    /**
     * The deposit voucher becomes the expense receipt (kept as a copy so both
     * modules can show the file from their own media collection).
     */
    private function copyVoucherToExpense(Deposit $deposit, Expense $expense): void
    {
        $voucher = $deposit->getFirstMedia('voucher');

        if (!$voucher || $expense->getMedia('receipt')->isNotEmpty()) {
            return;
        }

        $expense->copyMedia($voucher->getPath())
            ->usingFileName($voucher->file_name)
            ->toMediaCollection('receipt');
    }
}
