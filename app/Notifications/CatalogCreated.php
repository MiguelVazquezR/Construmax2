<?php

namespace App\Notifications;

use App\Models\BudgetCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CatalogCreated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly BudgetCatalog $catalog,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->catalog->budget->ticket;

        return (new MailMessage)
            ->subject('Catálogo de costos listo — ' . $ticket->folio)
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('Se ha generado un nuevo catálogo de costos (versión **' . $this->catalog->version . '**) para el ticket **' . $ticket->folio . '**.')
            ->line('Total: $' . number_format($this->catalog->total, 2))
            ->line('El catálogo está listo para enviarse al cliente.')
            ->action('Ver ticket', route('tickets.show', $ticket))
            ->line('Gracias.');
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->catalog->budget->ticket;

        return [
            'ticket_id'    => $ticket->id,
            'budget_id'    => $this->catalog->budget_id,
            'catalog_id'   => $this->catalog->id,
            'version'      => $this->catalog->version,
            'folio'        => $ticket->folio,
            'type'         => 'catalog.created',
            'route'        => 'tickets.show',
            'route_params' => ['ticket' => $ticket->id],
            'message'      => 'Catálogo de costos v' . $this->catalog->version . ' generado para ' . $ticket->folio . '.',
        ];
    }
}
