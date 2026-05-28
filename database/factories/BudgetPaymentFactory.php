<?php

namespace Database\Factories;

use App\Models\BudgetPayment;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetPaymentFactory extends Factory
{
    protected $model = BudgetPayment::class;

    public function definition(): array
    {
        return [
            'budget_id'      => Budget::factory(),
            'amount'         => fake()->randomFloat(2, 1000, 100000),
            'payment_date'   => fake()->dateTimeBetween('-6 months', 'now'),
            'reference'      => 'PAGO-' . strtoupper(fake()->bothify('??###')),
            'payment_method' => fake()->randomElement(['Transferencia', 'Cheque', 'Efectivo', 'Tarjeta']),
        ];
    }
}
