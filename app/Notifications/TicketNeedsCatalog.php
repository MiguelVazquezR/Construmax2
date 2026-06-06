<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNeedsCatalog extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nuevo catálogo necesario — ' . $this->ticket->folio)
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('El ticket **' . $this->ticket->folio . '** (' . $this->ticket->name . ') ha cambiado a estado **Catálogo**.')
            ->line('Se necesita generar un catálogo de costos para enviar al cliente.')
            ->action('Ir a costos', route('costs.index'))
            ->line('Gracias.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id'    => $this->ticket->id,
            'folio'        => $this->ticket->folio,
            'name'         => $this->ticket->name,
            'type'         => 'ticket.needs-catalog',
            'route'        => 'costs.index',
            'route_params' => [],
            'message'      => 'El ticket ' . $this->ticket->folio . ' necesita catálogo de costos.',
        ];
    }
}
