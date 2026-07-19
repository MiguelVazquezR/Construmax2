<?php

namespace Tests\Feature;

use App\Models\FieldWorkSchedule;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldWorkScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
    }

    // -----------------------------------------------------------------------
    // availableTickets
    // -----------------------------------------------------------------------

    public function test_available_tickets_returns_only_work_in_progress_and_unscheduled(): void
    {
        // A ticket in "Proceso de ejecución" that is NOT scheduled
        $eligible = Ticket::factory()->procesoEjecucion()->create();

        // A ticket in "Proceso de ejecución" that IS already scheduled
        $alreadyScheduled = Ticket::factory()->procesoEjecucion()->create();
        FieldWorkSchedule::factory()->create(['ticket_id' => $alreadyScheduled->id, 'user_id' => $this->user->id]);

        // A ticket in a different status
        $wrongStatus = Ticket::factory()->borrador()->create();

        $this->actingAs($this->user)
            ->get(route('field-work.available-tickets'))
            ->assertOk()
            ->assertJsonFragment(['id' => $eligible->id])
            ->assertJsonMissing(['id' => $alreadyScheduled->id])
            ->assertJsonMissing(['id' => $wrongStatus->id]);
    }

    public function test_available_tickets_includes_related_data(): void
    {
        $ticket = Ticket::factory()->procesoEjecucion()->create();

        $this->actingAs($this->user)
            ->get(route('field-work.available-tickets'))
            ->assertOk()
            ->assertJsonFragment([
                'id'   => $ticket->id,
                'name' => $ticket->name,
            ]);
    }

    // -----------------------------------------------------------------------
    // events
    // -----------------------------------------------------------------------

    public function test_events_returns_all_field_work_schedules(): void
    {
        $schedule = FieldWorkSchedule::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get(route('field-work.events'))
            ->assertOk()
            ->assertJsonFragment([
                'id'        => $schedule->id,
                'ticket_id' => $schedule->ticket_id,
            ]);
    }

    // -----------------------------------------------------------------------
    // store
    // -----------------------------------------------------------------------

    public function test_store_creates_schedule_and_syncs_task_timestamps(): void
    {
        $ticket = Ticket::factory()->procesoEjecucion()->create();

        // Create 3 tasks ordered by ID
        $task1 = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $this->user->id]);
        $task2 = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $this->user->id]);
        $task3 = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $this->user->id]);

        $startTime = now()->addDay()->setHour(9)->setMinute(0)->setSecond(0);
        $endTime   = now()->addDay()->setHour(17)->setMinute(0)->setSecond(0);

        $this->actingAs($this->user)
            ->post(route('field-work.store'), [
                'ticket_id'  => $ticket->id,
                'start_time' => $startTime->toDateTimeString(),
                'end_time'   => $endTime->toDateTimeString(),
                'color'      => '#FF5733',
                'notes'      => 'Test notes',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Assert schedule was created
        $this->assertDatabaseHas('field_work_schedules', [
            'ticket_id' => $ticket->id,
            'user_id'   => $this->user->id,
            'color'     => '#FF5733',
            'notes'     => 'Test notes',
        ]);

        // Assert tasks 1-2 (N-1) got start_date = schedule start_time
        $this->assertEquals(
            $startTime->toDateTimeString(),
            $task1->fresh()->start_date->toDateTimeString(),
        );
        $this->assertEquals(
            $startTime->toDateTimeString(),
            $task2->fresh()->start_date->toDateTimeString(),
        );

        // Assert task 3 (last) got due_date = schedule end_time
        $this->assertEquals(
            $startTime->toDateTimeString(),
            $task3->fresh()->start_date->toDateTimeString(),
        );
        $this->assertEquals(
            $endTime->toDateTimeString(),
            $task3->fresh()->due_date->toDateTimeString(),
        );
    }

    public function test_store_fails_when_end_before_start(): void
    {
        $ticket = Ticket::factory()->procesoEjecucion()->create();

        $this->actingAs($this->user)
            ->post(route('field-work.store'), [
                'ticket_id'  => $ticket->id,
                'start_time' => now()->addDays(2)->toDateTimeString(),
                'end_time'   => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('end_time');
    }

    public function test_store_requires_ticket_id(): void
    {
        $this->actingAs($this->user)
            ->post(route('field-work.store'), [
                'start_time' => now()->addDay()->toDateTimeString(),
                'end_time'   => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('ticket_id');
    }

    public function test_store_fails_for_duplicate_ticket(): void
    {
        $ticket = Ticket::factory()->procesoEjecucion()->create();

        // First schedule
        FieldWorkSchedule::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $this->user->id,
        ]);

        // Second schedule for same ticket should fail at DB level (unique constraint)
        // The test expects a 500-level error from the unique constraint violation
        $this->actingAs($this->user)
            ->from(route('calendar.index'))
            ->post(route('field-work.store'), [
                'ticket_id'  => $ticket->id,
                'start_time' => now()->addDay()->toDateTimeString(),
                'end_time'   => now()->addDay()->addHours(2)->toDateTimeString(),
            ])
            ->assertStatus(500);
    }

    // -----------------------------------------------------------------------
    // update
    // -----------------------------------------------------------------------

    public function test_update_modifies_schedule_and_re_syncs_tasks(): void
    {
        $ticket = Ticket::factory()->procesoEjecucion()->create();
        $task1 = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $this->user->id]);

        $schedule = FieldWorkSchedule::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $this->user->id,
        ]);

        $newStart = now()->addDays(3)->setHour(8)->setMinute(0)->setSecond(0);
        $newEnd   = now()->addDays(3)->setHour(16)->setMinute(0)->setSecond(0);

        $this->actingAs($this->user)
            ->put(route('field-work.update', $schedule), [
                'start_time' => $newStart->toDateTimeString(),
                'end_time'   => $newEnd->toDateTimeString(),
                'color'      => '#00FF00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_work_schedules', [
            'id'    => $schedule->id,
            'color' => '#00FF00',
        ]);

        // Task (last and only) should have due_date updated
        $this->assertEquals(
            $newEnd->toDateTimeString(),
            $task1->fresh()->due_date->toDateTimeString(),
        );
    }

    // -----------------------------------------------------------------------
    // destroy
    // -----------------------------------------------------------------------

    public function test_destroy_deletes_schedule_and_clears_task_timestamps(): void
    {
        $ticket = Ticket::factory()->procesoEjecucion()->create();
        $task = TicketTask::factory()->create([
            'ticket_id'  => $ticket->id,
            'user_id'    => $this->user->id,
            'start_date' => now(),
            'due_date'   => now()->addHours(4),
        ]);

        $schedule = FieldWorkSchedule::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('field-work.destroy', $schedule))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('field_work_schedules', ['id' => $schedule->id]);

        // Task timestamps should be cleared
        $this->assertNull($task->fresh()->start_date);
        $this->assertNull($task->fresh()->due_date);
    }

    // -----------------------------------------------------------------------
    // Automation: task sync with multiple tasks
    // -----------------------------------------------------------------------

    public function test_task_sync_handles_single_task(): void
    {
        $this->actingAs($this->user);

        $ticket = Ticket::factory()->procesoEjecucion()->create();
        $task = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $this->user->id]);

        $startTime = now()->addDay()->setHour(10, 0, 0);
        $endTime   = now()->addDay()->setHour(14, 0, 0);

        // Create via action directly to test the service
        $action = app(\App\Actions\FieldWork\CreateFieldWorkScheduleAction::class);
        $action->execute([
            'ticket_id'  => $ticket->id,
            'start_time' => $startTime->toDateTimeString(),
            'end_time'   => $endTime->toDateTimeString(),
        ]);

        // Single task = last task = gets both start and due_date
        $fresh = $task->fresh();
        $this->assertEquals($startTime->toDateTimeString(), $fresh->start_date->toDateTimeString());
        $this->assertEquals($endTime->toDateTimeString(), $fresh->due_date->toDateTimeString());
    }

    public function test_task_sync_handles_many_tasks(): void
    {
        $this->actingAs($this->user);

        $ticket = Ticket::factory()->procesoEjecucion()->create();

        // Create 5 tasks
        $tasks = [];
        for ($i = 0; $i < 5; $i++) {
            $tasks[] = TicketTask::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id'   => $this->user->id,
            ]);
        }

        $startTime = now()->addDay()->setHour(8, 0, 0);
        $endTime   = now()->addDay()->setHour(18, 0, 0);

        $action = app(\App\Actions\FieldWork\CreateFieldWorkScheduleAction::class);
        $action->execute([
            'ticket_id'  => $ticket->id,
            'start_time' => $startTime->toDateTimeString(),
            'end_time'   => $endTime->toDateTimeString(),
        ]);

        // Tasks 0-3 (N-1) should have start_date = start_time
        for ($i = 0; $i < 4; $i++) {
            $fresh = $tasks[$i]->fresh();
            $this->assertEquals(
                $startTime->toDateTimeString(),
                $fresh->start_date->toDateTimeString(),
                "Task {$i} start_date mismatch",
            );
        }

        // Task 4 (last) should have due_date = end_time
        $last = $tasks[4]->fresh();
        $this->assertEquals($startTime->toDateTimeString(), $last->start_date->toDateTimeString());
        $this->assertEquals($endTime->toDateTimeString(), $last->due_date->toDateTimeString());
    }
}
