<?php

namespace App\Notifications;

use App\Models\VacationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VacationRequested extends Notification
{
    use Queueable;

    public function __construct(
        public readonly VacationRequest $vacationRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->vacationRequest->loadMissing('user');

        return (new MailMessage)
            ->subject('Nueva solicitud de vacaciones — '.$request->user->name)
            ->greeting('Hola '.$notifiable->name.',')
            ->line($request->user->name.' solicitó **'.(float) $request->days.'** día(s) de vacaciones.')
            ->line('Periodo: del '.$request->start_date->format('d/m/Y').' al '.$request->end_date->format('d/m/Y').'.')
            ->when($request->reason, fn ($message) => $message->line('Motivo: '.$request->reason))
            ->action('Revisar solicitud', route('payroll.vacations.index'))
            ->line('Gracias.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vacation_request_id' => $this->vacationRequest->id,
            'user_id' => $this->vacationRequest->user_id,
            'days' => (float) $this->vacationRequest->days,
            'start_date' => $this->vacationRequest->start_date->toDateString(),
            'end_date' => $this->vacationRequest->end_date->toDateString(),
            'type' => 'vacation.requested',
            'route' => 'payroll.vacations.index',
            'route_params' => [],
            'message' => 'Solicitud de vacaciones pendiente de revisión.',
        ];
    }
}
