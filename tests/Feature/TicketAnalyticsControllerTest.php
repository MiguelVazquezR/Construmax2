<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetPayment;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerContact;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_index_renders_ticket_analytics_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get(route('tickets.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('TicketsDashboard')
                ->has('kpis')
                ->has('charts')
                ->has('tables')
                ->has('filters')
            );
    }

    public function test_index_accepts_date_range_filters(): void
    {
        $this->actingAs($this->user)
            ->get(route('tickets.dashboard', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ]))
            ->assertInertia(fn ($page) => $page
                ->component('TicketsDashboard')
            );
    }

    public function test_index_filters_by_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.dashboard', [
                'customer_id' => $customer->id,
            ]))
            ->assertInertia(fn ($page) => $page
                ->component('TicketsDashboard')
            );
    }

    public function test_index_filters_by_seller(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($this->user)
            ->get(route('tickets.dashboard', [
                'seller_id' => $seller->id,
            ]))
            ->assertInertia(fn ($page) => $page
                ->component('TicketsDashboard')
            );
    }

    public function test_index_calculates_kpis_with_data(): void
    {
        $customer = Customer::factory()->create();
        $branch = CustomerBranch::factory()->create(['customer_id' => $customer->id]);
        $contact = CustomerContact::factory()->create(['customer_id' => $customer->id]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'customer_branch_id' => $branch->id,
            'customer_contact_id' => $contact->id,
            'scheduled_start' => now(),
            'scheduled_end' => now()->addDays(7),
        ]);

        TicketTask::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->user->id,
            'start_date' => now(),
            'due_date' => now()->addDays(3),
            'status' => 'Completada',
        ]);

        Budget::factory()->create(['ticket_id' => $ticket->id]);

        $this->actingAs($this->user)
            ->get(route('tickets.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('TicketsDashboard')
                ->has('kpis')
                ->has('charts')
            );
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('tickets.dashboard'))
            ->assertRedirect('/login');
    }
}
