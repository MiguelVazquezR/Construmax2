<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Common expense categories for the expenses module.
     */
    private array $categories = [
        'Renta de oficina',
        'Servicios (luz, agua, internet)',
        'Insumos y materiales',
        'Papelería y consumibles',
        'Transporte y combustible',
        'Viáticos',
        'Mantenimiento de vehículos',
        'Herramienta y equipo',
        'Honorarios y servicios externos',
        'Otros gastos',
    ];

    public function run(): void
    {
        foreach ($this->categories as $name) {
            ExpenseCategory::firstOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }

        $this->command?->info('Categorías de gasto creadas correctamente.');
    }
}
