<?php

namespace Database\Factories;

use App\Models\TaskTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskTemplateFactory extends Factory
{
    protected $model = TaskTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(2),
            'description' => fake()->optional()->paragraph(),
            'is_active' => true,
        ];
    }
}
