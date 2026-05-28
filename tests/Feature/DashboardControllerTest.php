<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('my_day')
                ->has('kpis')
            );
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect('/login');
    }

    public function test_dashboard_shows_todays_events(): void
    {
        Calendar::factory()->create([
            'user_id' => $this->user->id,
            'start_time' => now(),
            'end_time' => now()->addHours(1),
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
            );
    }

    public function test_dashboard_shows_pending_tickets(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'En proceso']);
        $ticket->tasks()->create([
            'name' => 'Test task',
            'user_id' => $this->user->id,
            'start_date' => now(),
            'due_date' => now()->addDays(5),
            'status' => 'Pendiente',
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
            );
    }
}
