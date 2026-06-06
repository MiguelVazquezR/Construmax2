<?php

namespace App\Notifications;

use App\Models\Budget;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdue extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly Budget $budget,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paymentDays = $this->ticket->customer->payment_days ?? 0;
        $dueDate = $this->budget->invoice_date
            ? $this->budget->invoice_date->copy()->addDays($paymentDays)->format('d/m/Y')
            : 'N/A';

        return (new MailMessage)
            ->subject('Factura vence hoy — ' . $this->ticket->folio)
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('La factura del ticket **' . $this->ticket->folio . '** (' . $this->ticket->name . ') vence hoy.')
            ->line('El cliente **' . ($this->ticket->customer->name ?? 'N/A') . '** tiene ' . $paymentDays . ' días de crédito y el plazo de pago ha llegado.')
            ->line('Fecha de factura: ' . ($this->budget->invoice_date?->format('d/m/Y') ?? 'N/A'))
            ->line('Fecha de vencimiento: ' . $dueDate)
            ->line('Por favor realiza el seguimiento con el cliente para el cobro.')
            ->action('Ir al presupuesto', route('budgets.show', $this->budget))
            ->line('Gracias.');
    }

    public function toArray(object $notifiable): array
    {
        $paymentDays = $this->ticket->customer->payment_days ?? 0;

        return [
            'ticket_id'    => $this->ticket->id,
            'budget_id'    => $this->budget->id,
            'folio'        => $this->ticket->folio,
            'name'         => $this->ticket->name,
            'customer'     => $this->ticket->customer->name ?? 'N/A',
            'payment_days' => $paymentDays,
            'type'         => 'invoice.overdue',
            'route'        => 'budgets.show',
            'route_params' => ['budget' => $this->budget->id],
            'message'      => 'Factura vencida: ' . $this->ticket->folio . ' (' . ($this->ticket->customer->name ?? 'N/A') . ', ' . $paymentDays . ' días de crédito).',
        ];
    }
}
