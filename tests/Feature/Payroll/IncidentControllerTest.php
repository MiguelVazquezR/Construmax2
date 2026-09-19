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

    public function test_index_renders_the_incidents_page(): void
    {
        Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_ABSENCE_JUSTIFIED,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.incidents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Incidents/Index')
                ->has('incidents.data', 1)
                ->has('users')
                ->has('types')
            );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->employee)
            ->get(route('payroll.incidents.index'))
            ->assertForbidden();
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
