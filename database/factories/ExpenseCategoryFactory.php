<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Renta de oficina',
                'Servicios',
                'Insumos y materiales',
                'Papelería y consumibles',
                'Transporte y combustible',
                'Viáticos',
                'Mantenimiento de vehículos',
                'Herramienta y equipo',
                'Honorarios y servicios externos',
                'Otros gastos',
            ]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
