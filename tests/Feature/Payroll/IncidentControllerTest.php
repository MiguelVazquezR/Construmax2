<?php

namespace Tests\Feature\Payroll;

use App\Models\Incident;
use App\Models\PayrollProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class IncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.incidents.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage incidents']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.incidents.manage');

        $this->employee = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'hire_date' => '2026-01-01',
            'is_attendance_subject' => true,
        ]);
    }

    public function test_store_creates_an_incident_with_computed_days(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.incidents.store'), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_ABSENCE_UNJUSTIFIED,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
                'notes' => 'No se presentó',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $incident = Incident::first();

        $this->assertNotNull($incident);
        $this->assertSame(2.0, (float) $incident->days);
        $this->assertSame(Incident::STATUS_APPROVED, $incident->status);
        $this->assertSame($this->admin->id, $incident->created_by);
        $this->assertNotNull($incident->approved_at);
    }

    public function test_store_uses_the_start_date_when_the_end_is_omitted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.incidents.store'), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_PERMISSION_PAID,
                'start_date' => '2026-09-14',
            ])
            ->assertRedirect();

        $incident = Incident::first();

        $this->assertSame('2026-09-14', $incident->end_date->toDateString());
        $this->assertSame(1.0, (float) $incident->days);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.incidents.store'), [])
            ->assertSessionHasErrors(['user_id', 'type', 'start_date']);
    }

    public function test_store_rejects_an_incident_overlapping_another_one(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.incidents.store'), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_OTHER,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
            ])
            ->assertRedirect();

        // Any overlapping combination is rejected (start inside, end inside, fully covered).
        foreach ([['2026-09-15', '2026-09-15'], ['2026-09-13', '2026-09-14'], ['2026-09-14', '2026-09-16']] as [$start, $end]) {
            $this->actingAs($this->admin)
                ->post(route('payroll.incidents.store'), [
                    'user_id' => $this->employee->id,
                    'type' => Incident::TYPE_OTHER,
                    'start_date' => $start,
                    'end_date' => $end,
                ])
                ->assertSessionHasErrors('start_date');
        }

        $this->assertDatabaseCount('incidents', 1);

        // Adjacent days keep working, and so do other collaborators.
        $this->actingAs($this->admin)
            ->post(route('payroll.incidents.store'), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_OTHER,
                'start_date' => '2026-09-16',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('payroll.incidents.store'), [
                'user_id' => $other->id,
                'type' => Incident::TYPE_OTHER,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('incidents', 3);
    }

    public function test_update_modifies_the_incident_and_recomputes_the_days(): void
    {
        $incident = Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        // Weekending itself must not be read as an overlap.
        $this->actingAs($this->admin)
            ->put(route('payroll.incidents.update', $incident), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_PERMISSION_PAID,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-16',
                'notes' => 'Permiso aprobado',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $incident->refresh();

        $this->assertSame(Incident::TYPE_PERMISSION_PAID, $incident->type);
        $this->assertSame('2026-09-14', $incident->start_date->toDateString());
        $this->assertSame('2026-09-16', $incident->end_date->toDateString());
        $this->assertSame(3.0, (float) $incident->days);
        $this->assertSame('Permiso aprobado', $incident->notes);
    }

    public function test_update_rejects_overlapping_another_incident(): void
    {
        Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $second = Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-16',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->admin)
            ->put(route('payroll.incidents.update', $second), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_OTHER,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-15',
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertSame('2026-09-16', $second->fresh()->start_date->toDateString());
    }

    public function test_update_requires_the_permission(): void
    {
        $incident = Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->employee)
            ->put(route('payroll.incidents.update', $incident), [
                'user_id' => $this->employee->id,
                'type' => Incident::TYPE_OTHER,
                'start_date' => '2026-09-14',
            ])
            ->assertForbidden();
    }

    public function test_destroy_deletes_the_incident(): void
    {
        $incident = Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('payroll.incidents.destroy', $incident))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('incidents', ['id' => $incident->id]);
    }

    public function test_destroy_requires_the_permission(): void
    {
        $incident = Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->employee)
            ->delete(route('payroll.incidents.destroy', $incident))
            ->assertForbidden();
    }
}
