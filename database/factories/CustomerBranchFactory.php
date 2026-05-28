<?php

namespace Database\Factories;

use App\Models\CustomerBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerBranchFactory extends Factory
{
    protected $model = CustomerBranch::class;

    private static array $mexicoStates = [
        'Ciudad de México', 'Nuevo León', 'Jalisco', 'Estado de México', 'Querétaro',
        'Guanajuato', 'Puebla', 'Yucatán', 'Baja California', 'Sonora',
        'Chihuahua', 'Tamaulipas', 'Veracruz', 'Sinaloa', 'Michoacán',
        'Oaxaca', 'Chiapas', 'Guerrero', 'San Luis Potosí', 'Coahuila',
        'Quintana Roo', 'Hidalgo', 'Tabasco', 'Zacatecas', 'Durango',
        'Aguascalientes', 'Colima', 'Nayarit', 'Morelos', 'Campeche',
        'Baja California Sur', 'Tlaxcala',
    ];

    private static array $usStates = [
        'Texas', 'California', 'Florida', 'Arizona', 'New Mexico',
    ];

    private static array $countries = ['México', 'Estados Unidos'];

    public function definition(): array
    {
        $country = fake()->randomElement(self::$countries);
        $region = $country === 'México'
            ? fake()->randomElement(self::$mexicoStates)
            : fake()->randomElement(self::$usStates);

        return [
            'country'     => $country,
            'region'      => $region,
            'unit'        => fake()->randomElement([
                'Centro Comercial', 'Planta', 'Oficinas Corporativas', 'Sucursal', 'Bodega',
                'Piso', 'Nave Industrial', 'Hotel', 'Hospital', 'Tienda',
            ]) . ' ' . fake()->citySuffix(),
            'branch_name' => fake()->company() . ' ' . fake()->randomElement([
                'Sucursal', 'Planta', 'Oficina', 'Unidad', 'Agencia',
            ]) . ' ' . $region,
        ];
    }

    public function mexico(): static
    {
        return $this->state(fn () => [
            'country' => 'México',
            'region'  => fake()->randomElement(self::$mexicoStates),
        ]);
    }

    public function usa(): static
    {
        return $this->state(fn () => [
            'country' => 'Estados Unidos',
            'region'  => fake()->randomElement(self::$usStates),
        ]);
    }
}
