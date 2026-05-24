<?php

namespace Database\Factories;

use App\Models\BudgetConcept;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetConceptFactory extends Factory
{
    protected $model = BudgetConcept::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'concept'   => fake()->randomElement([
                'Mano de obra', 'Materiales', 'Equipo especializado', 'Transporte',
                'Supervisión', 'Andamios y elevación', 'Herramienta menor', 'Flete',
                'Viáticos', 'Permisos y licencias', 'Subcontratación', 'Renta de equipo',
            ]),
            'amount'    => fake()->randomFloat(2, 500, 80000),
        ];
    }
}
