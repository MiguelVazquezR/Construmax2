<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DepositPendingApproval extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Deposit $deposit,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'deposit_id'   => $this->deposit->id,
            'technician'   => $this->deposit->technician->user->name,
            'amount'       => number_format($this->deposit->amount, 2),
            'type'         => 'deposit.pending-approval',
            'route'        => 'deposits.index',
            'route_params' => [],
            'title'        => 'Nuevo depósito pendiente de aprobación',
            'message'      => "Depósito para {$this->deposit->technician->user->name} por $" . number_format($this->deposit->amount, 2),
        ];
    }
}
