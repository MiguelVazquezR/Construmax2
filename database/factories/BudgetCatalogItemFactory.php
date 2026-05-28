<?php

namespace Database\Factories;

use App\Models\BudgetCatalogItem;
use App\Models\BudgetCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetCatalogItemFactory extends Factory
{
    protected $model = BudgetCatalogItem::class;

    private static array $descriptions = [
        'Suministro e instalación de luminaria LED', 'Mantenimiento a equipo de aire acondicionado',
        'Pintura vinílica en muros interiores', 'Reparación de plafón', 'Impermeabilización de azotea',
        'Instalación de contactos eléctricos', 'Cambio de vidrio templado', 'Soldadura de estructura metálica',
        'Limpieza de ductos', 'Instalación de cámara CCTV', 'Mantenimiento de elevador',
        'Reparación de fuga sanitaria', 'Aplicación de sellador', 'Desmontaje de equipo obsoleto',
        'Suministro de material eléctrico', 'Fabricación de cancelería de aluminio',
        'Nivelación de piso', 'Instalación de piso cerámico', 'Pulido de mármol',
        'Mantenimiento preventivo general',
    ];

    private static array $units = ['pza', 'm²', 'lote', 'servicio', 'kg', 'm', 'hr', 'juego'];

    public function definition(): array
    {
        $quantity   = fake()->randomFloat(2, 1, 50);
        $unitPrice  = fake()->randomFloat(2, 50, 15000);

        return [
            'budget_catalog_id' => BudgetCatalog::factory(),
            'description'       => fake()->randomElement(self::$descriptions),
            'unit'              => fake()->randomElement(self::$units),
            'quantity'          => $quantity,
            'unit_price'        => $unitPrice,
            'total'             => round($quantity * $unitPrice, 2),
        ];
    }
}
