<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->ticket = Ticket::factory()->create();
    }

    // --- store ---

    public function test_store_creates_task(): void
    {
        $start = now()->addDay()->toDateTimeString();
        $end = now()->addDay()->addHours(4)->toDateTimeString();

        $this->actingAs($this->user)
            ->post(route('tickets.tasks.store', $this->ticket), [
                'name' => 'Install wiring',
                'description' => 'Install new wiring',
                'user_id' => $this->user->id,
                'start_date' => $start,
                'due_date' => $end,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_tasks', [
            'ticket_id' => $this->ticket->id,
            'name' => 'Install wiring',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_fails_when_due_before_start(): void
    {
        $start = now()->addDays(2)->toDateTimeString();
        $end = now()->addDay()->toDateTimeString();

        $this->actingAs($this->user)
            ->post(route('tickets.tasks.store', $this->ticket), [
                'name' => 'Bad task',
                'user_id' => $this->user->id,
                'start_date' => $start,
                'due_date' => $end,
            ])
            ->assertSessionHasErrors('due_date');
    }

    public function test_store_requires_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('tickets.tasks.store', $this->ticket), [])
            ->assertSessionHasErrors(['name', 'user_id', 'start_date', 'due_date']);
    }

    // --- update ---

    public function test_update_modifies_task(): void
    {
        $start = now()->addDay()->toDateTimeString();
        $end = now()->addDay()->addHours(3)->toDateTimeString();

        $task = TicketTask::factory()->create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('tickets.tasks.update', $task), [
                'name' => 'Updated task',
                'user_id' => $this->user->id,
                'start_date' => $start,
                'due_date' => $end,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_tasks', [
            'id' => $task->id,
            'name' => 'Updated task',
        ]);
    }

    // --- destroy ---

    public function test_destroy_deletes_task(): void
    {
        $task = TicketTask::factory()->create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('tickets.tasks.destroy', $task))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ticket_tasks', ['id' => $task->id]);
    }

    // --- toggleComplete ---

    public function test_toggle_complete_flips_status(): void
    {
        $task = TicketTask::factory()->create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'status' => 'Pendiente',
        ]);

        $this->actingAs($this->user)
            ->put(route('tickets.tasks.toggle', $task))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_tasks', [
            'id' => $task->id,
            'status' => 'Completada',
        ]);

        // Toggle back
        $this->actingAs($this->user)
            ->put(route('tickets.tasks.toggle', $task))
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_tasks', [
            'id' => $task->id,
            'status' => 'Pendiente',
        ]);
    }
}
