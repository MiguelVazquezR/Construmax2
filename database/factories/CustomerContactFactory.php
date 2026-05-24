<?php

namespace Database\Factories;

use App\Models\CustomerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerContactFactory extends Factory
{
    protected $model = CustomerContact::class;

    private static array $positions = [
        'Gerente General', 'Gerente de Operaciones', 'Gerente de Mantenimiento',
        'Jefe de Compras', 'Coordinador de Servicios', 'Director de Planta',
        'Supervisor de Instalaciones', 'Gerente Administrativo', 'Encargado de Proyectos',
        'Director Regional', 'Jefe de Seguridad', 'Coordinador de Logística',
    ];

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName  = fake()->lastName();

        return [
            'name'     => $firstName . ' ' . $lastName,
            'email'    => strtolower($firstName . '.' . $lastName . '@' . fake()->safeEmailDomain()),
            'phone'    => fake()->numerify('55########'),
            'position' => fake()->randomElement(self::$positions),
        ];
    }
}
