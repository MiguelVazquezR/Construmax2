<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+2 weeks');

        return [
            'user_id'      => User::factory(),
            'title'        => fake()->sentence(3),
            'type'         => fake()->randomElement(['Reunión', 'Llamada', 'Visita', 'Entrega', 'Instalación']),
            'description'  => fake()->optional()->paragraph(),
            'start_time'   => $start,
            'end_time'     => (clone $start)->modify('+' . fake()->numberBetween(1, 4) . ' hours'),
            'is_completed' => false,
        ];
    }
}
