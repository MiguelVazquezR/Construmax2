<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\VacationPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VacationPremiumDue extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $collaborator,
        public readonly VacationPeriod $vacationPeriod,
        public readonly PayrollPeriod $payrollPeriod,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $anniversary = $this->vacationPeriod->end_date->copy()->addDay();

        return (new MailMessage)
            ->subject('Prima vacacional por pagar')
            ->greeting('Hola '.$notifiable->name.',')
            ->line($this->collaborator->name.' completa su año de servicio '.$this->vacationPeriod->year_number.' el '.$anniversary->format('d/m/Y').'.')
            ->line('El pago de la prima vacacional corresponde al periodo de nómina '.$this->payrollPeriod->label().'.')
            ->action('Ver periodos de vacaciones', route('payroll.vacations.index', ['user_id' => $this->collaborator->id]))
            ->line('Marca la prima como pagada en la tabla de periodos para detener los avisos.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vacation_period_id' => $this->vacationPeriod->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'type' => 'payroll.vacation-premium',
            'route' => 'payroll.vacations.index',
            'route_params' => ['user_id' => $this->collaborator->id],
            'message' => 'Toca pagar la prima vacacional de '.$this->collaborator->name
                .' (año de servicio '.$this->vacationPeriod->year_number.').',
        ];
    }
}
