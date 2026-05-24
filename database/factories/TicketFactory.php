<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    private static array $serviceTypes = [
        'Iluminación', 'Herrería', 'Acabados', 'Eléctrico', 'Aire acondicionado',
        'Sanitario', 'Anuncios', 'Pintura', 'Carpintería', 'Vidrio',
        'Aluminio', 'Protección civil STPS', 'Monta cargas', 'Control de plagas',
        'Impermeabilización', 'Servicios varios',
    ];

    private static array $priorities = ['Baja', 'Media', 'Alta', 'Urgente'];

    private static array $statuses = [
        'Borrador', 'Levantamiento', 'Catálogo', 'Proceso de ejecución',
        'Ejecutado', 'Facturado', 'Pagado', 'Cancelado',
    ];

    public function definition(): array
    {
        $scheduledStart = fake()->dateTimeBetween('-6 months', '+2 months');
        $scheduledEnd   = (clone $scheduledStart)->modify('+' . fake()->numberBetween(1, 30) . ' days');

        return [
            'customer_id'         => Customer::factory(),
            'customer_contact_id' => CustomerContact::factory(),
            'customer_branch_id'  => CustomerBranch::factory(),
            'seller_id'           => User::factory(),
            'name'                => fake()->sentence(3),
            'service_type'        => fake()->randomElement(self::$serviceTypes),
            'duration'            => fake()->randomElement(['1 semana', '2 semanas', '1 mes', '3 días', '2 meses', null]),
            'technicians'         => null,
            'status'              => fake()->randomElement(self::$statuses),
            'priority'            => fake()->randomElement(self::$priorities),
            'scheduled_start'     => $scheduledStart,
            'scheduled_end'       => $scheduledEnd,
            'instructions'        => fake()->optional(0.6)->paragraph(),
        ];
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function borrador(): static
    {
        return $this->withStatus('Borrador');
    }

    public function levantamiento(): static
    {
        return $this->withStatus('Levantamiento');
    }

    public function catalogo(): static
    {
        return $this->withStatus('Catálogo');
    }

    public function procesoEjecucion(): static
    {
        return $this->withStatus('Proceso de ejecución');
    }

    public function ejecutado(): static
    {
        return $this->withStatus('Ejecutado');
    }

    public function facturado(): static
    {
        return $this->withStatus('Facturado');
    }

    public function pagado(): static
    {
        return $this->withStatus('Pagado');
    }
}
