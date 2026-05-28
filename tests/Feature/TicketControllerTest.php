<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerContact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    // --- index ---

    public function test_index_renders_tickets_page(): void
    {
        Ticket::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('tickets.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Index')
                ->has('tickets.data', 3)
            );
    }

    public function test_index_filters_by_customer(): void
    {
        $customer = Customer::factory()->create();
        Ticket::factory()->create(['customer_id' => $customer->id]);
        Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.index', ['customer' => $customer->id]))
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 1)
            );
    }

    public function test_index_filters_by_folio(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.index', ['folio' => (string) $ticket->id]))
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 1)
            );
    }

    // --- create ---

    public function test_create_renders_form(): void
    {
        $this->actingAs($this->user)
            ->get(route('tickets.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Create')
                ->has('users')
                ->has('customers')
                ->has('templates')
            );
    }

    // --- store ---

    public function test_store_creates_ticket(): void
    {
        $customer = Customer::factory()->create();
        $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);
        CustomerBranch::factory()->create(['customer_id' => $customer->id]);

        $data = [
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'name' => 'Repair AC unit',
            'service_type' => 'Aire acondicionado',
            'priority' => 'Alta',
        ];

        $this->actingAs($this->user)
            ->post(route('tickets.store'), $data)
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'customer_id' => $customer->id,
            'name' => 'Repair AC unit',
            'status' => 'Borrador',
        ]);
    }

    public function test_store_fails_validation_without_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('tickets.store'), [])
            ->assertSessionHasErrors([
                'customer_id', 'customer_contact_id', 'name',
                'service_type', 'priority',
            ]);
    }

    // --- show ---

    public function test_show_displays_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.show', $ticket))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->has('ticket')
            );
    }

    // --- updateStatus ---

    public function test_update_status_changes_ticket_status(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'Borrador']);

        $this->actingAs($this->user)
            ->put(route('tickets.update-status', $ticket), ['status' => 'Levantamiento'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'Levantamiento',
        ]);
    }

    // --- edit ---

    public function test_edit_renders_form(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.edit', $ticket))
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Edit')
                ->has('ticket')
            );
    }

    // --- update ---

    public function test_update_modifies_ticket(): void
    {
        $ticket = Ticket::factory()->create(['name' => 'Old name']);
        $customer = Customer::factory()->create();
        $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->user)
            ->put(route('tickets.update', $ticket), [
                'customer_id' => $customer->id,
                'customer_contact_id' => $contact->id,
                'name' => 'Updated name',
                'service_type' => 'Pintura',
                'priority' => 'Media',
                'status' => 'Levantamiento',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'name' => 'Updated name',
        ]);
    }

    // --- destroy ---

    public function test_destroy_deletes_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('tickets.destroy', $ticket))
            ->assertRedirect(route('tickets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }
}
