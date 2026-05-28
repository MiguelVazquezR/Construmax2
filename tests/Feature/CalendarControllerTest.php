<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->otherUser = User::factory()->create(['is_active' => true]);
    }

    // --- overview ---

    public function test_overview_returns_json_with_counts(): void
    {
        $this->actingAs($this->user)
            ->get(route('calendar.overview'))
            ->assertJsonStructure(['invitations', 'today_events', 'total']);
    }

    // --- index ---

    public function test_index_renders_calendar_page(): void
    {
        Calendar::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Calendar/Index')
                ->has('events')
                ->has('users')
            );
    }

    // --- store ---

    public function test_store_creates_event(): void
    {
        $start = now()->addDay()->toDateTimeString();
        $end = now()->addDay()->addHours(2)->toDateTimeString();

        $data = [
            'title' => 'Team meeting',
            'type' => 'Reunión',
            'start_time' => $start,
            'end_time' => $end,
            'description' => 'Monthly team sync',
            'participants' => [$this->otherUser->id],
        ];

        $this->actingAs($this->user)
            ->post(route('calendar.store'), $data)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('calendars', [
            'title' => 'Team meeting',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_fails_when_end_before_start(): void
    {
        $start = now()->addDays(2)->toDateTimeString();
        $end = now()->addDay()->toDateTimeString();

        $this->actingAs($this->user)
            ->post(route('calendar.store'), [
                'title' => 'Bad event',
                'type' => 'Reunión',
                'start_time' => $start,
                'end_time' => $end,
            ])
            ->assertSessionHasErrors('end_time');
    }

    public function test_store_requires_title_and_type(): void
    {
        $this->actingAs($this->user)
            ->post(route('calendar.store'), [])
            ->assertSessionHasErrors(['title', 'type', 'start_time', 'end_time']);
    }

    // --- update ---

    public function test_update_modifies_event(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->user->id]);
        $start = now()->addDay()->toDateTimeString();
        $end = now()->addDay()->addHours(3)->toDateTimeString();

        $this->actingAs($this->user)
            ->put(route('calendar.update', $calendar), [
                'title' => 'Updated meeting',
                'type' => 'Llamada',
                'description' => 'Updated description',
                'start_time' => $start,
                'end_time' => $end,
                'participants' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'title' => 'Updated meeting',
        ]);
    }

    public function test_update_forbidden_for_non_creator(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user)
            ->put(route('calendar.update', $calendar), [
                'title' => 'Hijack',
                'type' => 'Reunión',
                'description' => 'Trying to hijack',
                'start_time' => now()->addDay()->toDateTimeString(),
                'end_time' => now()->addDay()->addHours(1)->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    // --- toggleComplete ---

    public function test_toggle_complete_flips_status(): void
    {
        $calendar = Calendar::factory()->create([
            'user_id' => $this->user->id,
            'is_completed' => false,
        ]);

        $this->actingAs($this->user)
            ->put(route('calendar.toggle-complete', $calendar))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'is_completed' => true,
        ]);
    }

    // --- destroy ---

    public function test_destroy_deletes_event(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->delete(route('calendar.destroy', $calendar))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('calendars', ['id' => $calendar->id]);
    }

    public function test_destroy_forbidden_for_non_creator(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user)
            ->delete(route('calendar.destroy', $calendar))
            ->assertForbidden();
    }

    // --- respond ---

    public function test_respond_accepts_invitation(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->otherUser->id]);
        $calendar->participants()->attach($this->user->id, ['status' => 'Pendiente']);

        $this->actingAs($this->user)
            ->put(route('calendar.respond', $calendar), [
                'status' => 'Aceptado',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('calendar_participants', [
            'calendar_id' => $calendar->id,
            'user_id' => $this->user->id,
            'status' => 'Aceptado',
        ]);
    }

    public function test_respond_rejects_with_reason(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->otherUser->id]);
        $calendar->participants()->attach($this->user->id, ['status' => 'Pendiente']);

        $this->actingAs($this->user)
            ->put(route('calendar.respond', $calendar), [
                'status' => 'Rechazado',
                'rejection_reason' => 'Scheduling conflict',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('calendar_participants', [
            'calendar_id' => $calendar->id,
            'user_id' => $this->user->id,
            'status' => 'Rechazado',
            'rejection_reason' => 'Scheduling conflict',
        ]);
    }

    public function test_respond_requires_reason_when_rejecting(): void
    {
        $calendar = Calendar::factory()->create(['user_id' => $this->otherUser->id]);
        $calendar->participants()->attach($this->user->id, ['status' => 'Pendiente']);

        $this->actingAs($this->user)
            ->put(route('calendar.respond', $calendar), [
                'status' => 'Rechazado',
            ])
            ->assertSessionHasErrors('rejection_reason');
    }
}
