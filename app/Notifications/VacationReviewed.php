<?php

namespace App\Notifications;

use App\Models\VacationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VacationReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly VacationRequest $vacationRequest,
        public readonly bool $approved,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->vacationRequest;
        $period = 'del '.$request->start_date->format('d/m/Y').' al '.$request->end_date->format('d/m/Y');

        return (new MailMessage)
            ->subject($this->approved ? 'Vacaciones aprobadas' : 'Solicitud de vacaciones rechazada')
            ->greeting('Hola '.$notifiable->name.',')
            ->line($this->approved
                ? 'Tu solicitud de vacaciones ('.$period.') fue **aprobada**.'
                : 'Tu solicitud de vacaciones ('.$period.') fue **rechazada**.')
            ->when($request->review_notes, fn ($message) => $message->line('Notas: '.$request->review_notes))
            ->action('Ver mis vacaciones', route('payroll.vacations.index'))
            ->line('Gracias.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vacation_request_id' => $this->vacationRequest->id,
            'approved' => $this->approved,
            'start_date' => $this->vacationRequest->start_date->toDateString(),
            'end_date' => $this->vacationRequest->end_date->toDateString(),
            'type' => 'vacation.reviewed',
            'route' => 'payroll.vacations.index',
            'route_params' => [],
            'message' => $this->approved
                ? 'Tu solicitud de vacaciones fue aprobada.'
                : 'Tu solicitud de vacaciones fue rechazada.',
        ];
    }
}
