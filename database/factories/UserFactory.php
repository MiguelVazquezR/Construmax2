<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_team_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the model to also create a Technician profile.
     */
    public function asTechnician(array $technicianAttributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($technicianAttributes) {
            $user->technician()->create(array_merge([
                'phone'              => fake()->numerify('55########'),
                'secondary_phone'    => fake()->optional()->numerify('55########'),
                'is_internal'        => fake()->boolean(),
                'state'              => fake()->state(),
                'city'               => fake()->city(),
                'colony'             => fake()->optional()->word(),
                'zip_code'           => fake()->numerify('#####'),
                'coverage_radius_km' => fake()->numberBetween(5, 100),
                'specialties'        => fake()->randomElements(
                    \App\Models\Technician::SPECIALTIES,
                    fake()->numberBetween(1, 4)
                ),
                'legal_name'         => $user->name,
                'rfc'                => strtoupper(fake()->bothify('???######???')),
                'bank_name'          => fake()->randomElement(['BBVA', 'Banorte', 'Santander', 'Banamex', 'HSBC', 'Banregio']),
                'bank_account'       => fake()->numerify('############'),
                'clabe'              => fake()->numerify('##################'),
                'status'             => fake()->randomElement(['Activo', 'En revisión', 'Inactivo']),
                'rating_avg'         => fake()->randomFloat(2, 1, 5),
                'internal_notes'     => fake()->optional()->sentence(),
            ], $technicianAttributes));
        });
    }

    /**
     * Configure as internal technician.
     */
    public function internalTechnician(): static
    {
        return $this->asTechnician(['is_internal' => true]);
    }

    /**
     * Configure as external technician.
     */
    public function externalTechnician(): static
    {
        return $this->asTechnician(['is_internal' => false]);
    }

    /**
     * Indicate that the user should have a personal team.
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => $user->name.'\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}
