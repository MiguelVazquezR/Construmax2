<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollPeriodClosed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly PayrollPeriod $period,
        public readonly int $payslipsCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Periodo de nómina cerrado — '.$this->period->label())
            ->greeting('Hola '.$notifiable->name.',')
            ->line('El periodo de nómina **'.$this->period->label().'** fue cerrado automáticamente.')
            ->line('Se generaron **'.$this->payslipsCount.'** recibo(s).')
            ->line('Total neto: **$'.number_format((float) $this->period->total_net, 2).'**.')
            ->action('Ver periodo de nómina', route('payroll.periods.show', $this->period->id))
            ->line('El gasto correspondiente quedó registrado en Control de gastos.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payroll_period_id' => $this->period->id,
            'label' => $this->period->label(),
            'payslips_count' => $this->payslipsCount,
            'total_net' => (float) $this->period->total_net,
            'type' => 'payroll.period-closed',
            'route' => 'payroll.periods.show',
            'route_params' => ['period' => $this->period->id],
            'message' => 'El periodo '.$this->period->label().' fue cerrado: '.$this->payslipsCount.' recibo(s) generado(s).',
        ];
    }
}
