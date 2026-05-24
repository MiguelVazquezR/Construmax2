<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'ticket_id'      => Ticket::factory(),
            'status'         => fake()->randomElement(['Borrador', 'Enviado al cliente', 'Aprobado', 'Rechazado']),
            'description'    => fake()->optional()->paragraph(),
            'currency'       => fake()->randomElement(['MXN', 'USD']),
            'exchange_rate'  => 1.0000,
            'user_id'        => User::factory(),
            'invoice_date'   => null,
            'invoice_number' => null,
        ];
    }

    public function withInvoice(): static
    {
        return $this->state(fn () => [
            'invoice_date'   => fake()->dateTimeBetween('-3 months', 'now'),
            'invoice_number' => 'F-' . strtoupper(fake()->bothify('???-###-????')),
        ]);
    }
}
