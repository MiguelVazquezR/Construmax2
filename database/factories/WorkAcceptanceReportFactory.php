<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkAcceptanceReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkAcceptanceReportFactory extends Factory
{
    protected $model = WorkAcceptanceReport::class;

    public function definition(): array
    {
        return [
            'ticket_id'           => Ticket::factory(),
            'report_date'         => now()->toDateString(),
            'work_description'    => fake()->optional()->paragraph(),
            'on_site_start'       => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'on_site_end'         => fake()->optional()->dateTimeBetween('-1 day', 'now'),
            'technician_comments' => fake()->optional()->sentence(),
            'client_comments'     => null,
            'manager_name'        => null,
            'signature_data'      => null,
            'signatory_name'      => null,
            'signed_at'           => null,
            'is_signed'           => false,
            'created_by'          => User::factory(),
        ];
    }

    public function signed(): static
    {
        return $this->state(fn () => [
            'is_signed'       => true,
            'signed_at'       => now(),
            'signatory_name'  => fake()->name(),
            'signature_data'  => 'data:image/png;base64,' . base64_encode('fake-signature'),
        ]);
    }
}
