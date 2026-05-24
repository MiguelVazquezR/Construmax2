<?php

namespace Database\Factories;

use App\Models\TechnicianPayment;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnicianPaymentFactory extends Factory
{
    protected $model = TechnicianPayment::class;

    public function definition(): array
    {
        return [
            'budget_id'      => Budget::factory(),
            'user_id'        => User::factory(),
            'amount'         => fake()->randomFloat(2, 1000, 50000),
            'payment_date'   => fake()->dateTimeBetween('-6 months', 'now'),
            'payment_method' => fake()->randomElement(['Transferencia', 'Cheque', 'Efectivo']),
            'reference'      => 'TP-' . strtoupper(fake()->bothify('??####')),
            'notes'          => fake()->optional()->sentence(),
        ];
    }
}
