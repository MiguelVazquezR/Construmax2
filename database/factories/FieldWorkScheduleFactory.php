<?php

namespace Database\Factories;

use App\Models\FieldWorkSchedule;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldWorkScheduleFactory extends Factory
{
    protected $model = FieldWorkSchedule::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+2 weeks');
        $end   = (clone $start)->modify('+' . fake()->numberBetween(2, 8) . ' hours');

        return [
            'ticket_id'  => Ticket::factory()->procesoEjecucion(),
            'user_id'    => User::factory(),
            'start_time' => $start,
            'end_time'   => $end,
            'color'      => fake()->hexColor(),
            'notes'      => fake()->optional()->sentence(),
        ];
    }
}
