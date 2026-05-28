<?php

namespace Database\Factories;

use App\Models\BudgetCatalog;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetCatalogFactory extends Factory
{
    protected $model = BudgetCatalog::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5000, 250000);

        return [
            'budget_id' => Budget::factory(),
            'version'   => 1,
            'subtotal'  => $subtotal,
            'iva'       => round($subtotal * 0.16, 2),
            'total'     => round($subtotal * 1.16, 2),
        ];
    }

    public function withVersion(int $version): static
    {
        return $this->state(fn () => ['version' => $version]);
    }
}
