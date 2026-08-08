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
        if ($this->deposit->is_external) {
            $recipient = $this->deposit->external_beneficiary_name ?: 'Depósito externo';
            $title     = 'Nuevo depósito externo pendiente de aprobación';
            $message   = "Depósito externo para {$recipient} por $" . number_format($this->deposit->amount, 2);
        } else {
            $recipient = $this->deposit->technician?->user?->name ?: 'N/A';
            $title     = 'Nuevo depósito pendiente de aprobación';
            $message   = "Depósito para {$recipient} por $" . number_format($this->deposit->amount, 2);
        }

        return [
            'deposit_id'   => $this->deposit->id,
            'technician'   => $recipient,
            'amount'       => number_format($this->deposit->amount, 2),
            'type'         => 'deposit.pending-approval',
            'route'        => 'deposits.index',
            'route_params' => [],
            'title'        => $title,
            'message'      => $message,
        ];
    }
}