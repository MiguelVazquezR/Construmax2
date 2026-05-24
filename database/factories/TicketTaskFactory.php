<?php

namespace Database\Factories;

use App\Models\TicketTask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketTaskFactory extends Factory
{
    protected $model = TicketTask::class;

    private static array $taskNames = [
        'Levantamiento físico en sitio', 'Elaboración de catálogo de conceptos',
        'Instalación de equipo', 'Pruebas de funcionamiento', 'Limpieza de área de trabajo',
        'Desmontaje de equipo existente', 'Cableado estructurado', 'Configuración de sistema',
        'Aplicación de impermeabilizante', 'Pintura de acabado', 'Soldadura de estructura',
        'Instalación de ductos', 'Conexión eléctrica', 'Pruebas de presión',
        'Entrega y capacitación al cliente', 'Reporte fotográfico', 'Medición de parámetros',
        'Ajuste y calibración', 'Retiro de escombro', 'Instalación de accesorios',
    ];

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 months', '+1 month');

        return [
            'ticket_id'    => Ticket::factory(),
            'user_id'      => User::factory(),
            'name'         => fake()->randomElement(self::$taskNames),
            'description'  => fake()->optional()->sentence(),
            'status'       => fake()->randomElement(['Pendiente', 'En proceso', 'Completada']),
            'start_date'   => $startDate,
            'due_date'     => (clone $startDate)->modify('+' . fake()->numberBetween(1, 15) . ' days'),
            'completed_at' => null,
        ];
    }

    public function completada(): static
    {
        return $this->state(fn () => [
            'status'       => 'Completada',
            'completed_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }

    public function enProceso(): static
    {
        return $this->state(fn () => ['status' => 'En proceso']);
    }

    public function pendiente(): static
    {
        return $this->state(fn () => ['status' => 'Pendiente']);
    }
}
