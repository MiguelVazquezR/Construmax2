<?php

namespace App\Notifications;

use App\Models\BudgetCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CatalogNeedsUpdate extends Notification
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
            ->subject('Presupuesto actualizado, requiere nuevo catálogo — ' . $ticket->folio)
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('El presupuesto del ticket **' . $ticket->folio . '** (' . $ticket->name . ') ha sido **actualizado**.')
            ->line('El catálogo de costos (versión **' . $this->catalog->version . '**) quedó pendiente de actualización.')
            ->line('Guarda una nueva versión del catálogo para continuar con el flujo de aprobación.')
            ->action('Ir a costos', route('costs.show', $this->catalog->budget_id))
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
            'type'         => 'catalog.needs-update',
            'route'        => 'costs.show',
            'route_params' => ['budget' => $this->catalog->budget_id],
            'message'      => 'El presupuesto ' . $ticket->folio . ' fue actualizado. Guarda una nueva versión del catálogo v' . $this->catalog->version . '.',
        ];
    }
}
