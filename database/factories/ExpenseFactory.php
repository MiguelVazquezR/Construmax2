<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'ticket_id' => null,
            'concept' => fake()->sentence(3),
            'reference' => fake()->optional(0.5)->bothify('FAC-####'),
            'notes' => fake()->optional()->sentence(),
            'amount' => fake()->randomFloat(2, 100, 25000),
            'expense_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(array_keys(Expense::paymentMethodLabels())),
            'status' => Expense::STATUS_PENDING,
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => Expense::STATUS_PENDING,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Expense::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Expense::STATUS_CANCELLED,
            'paid_at' => null,
        ]);
    }
}
