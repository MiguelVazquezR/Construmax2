<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollNote;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.periods.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage payroll periods']);

        $this->admin = User::factory()->create(['is_active' => true, 'name' => 'Administradora Nómina']);
        $this->admin->givePermissionTo('payroll.periods.manage');

        $this->employee = User::factory()->create(['is_active' => true, 'name' => 'Empleado Comentado']);

        $this->period = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_store_creates_a_comment_for_the_collaborator(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.periods.notes.store', $this->period), [
                'user_id' => $this->employee->id,
                'body' => 'Se le adelantó el sueldo del viernes.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_notes', [
            'payroll_period_id' => $this->period->id,
            'user_id' => $this->employee->id,
            'body' => 'Se le adelantó el sueldo del viernes.',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_validates_the_body_and_the_collaborator(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.periods.notes.store', $this->period), [])
            ->assertSessionHasErrors(['user_id', 'body']);
    }

    public function test_store_requires_the_management_permission(): void
    {
        $this->actingAs($this->employee)
            ->post(route('payroll.periods.notes.store', $this->period), [
                'user_id' => $this->employee->id,
                'body' => 'Comentario sin permiso',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payroll_notes', 0);
    }

    public function test_update_edits_the_comment(): void
    {
        $note = $this->makeNote();

        $this->actingAs($this->admin)
            ->put(route('payroll.notes.update', $note), ['body' => 'Comentario corregido'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Comentario corregido', $note->fresh()->body);
    }

    public function test_update_requires_the_management_permission(): void
    {
        $note = $this->makeNote();

        $this->actingAs($this->employee)
            ->put(route('payroll.notes.update', $note), ['body' => 'Intento'])
            ->assertForbidden();

        $this->assertSame('Comentario original', $note->fresh()->body);
    }

    public function test_destroy_deletes_the_comment(): void
    {
        $note = $this->makeNote();

        $this->actingAs($this->admin)
            ->delete(route('payroll.notes.destroy', $note))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('payroll_notes', ['id' => $note->id]);
    }

    public function test_destroy_requires_the_management_permission(): void
    {
        $note = $this->makeNote();

        $this->actingAs($this->employee)
            ->delete(route('payroll.notes.destroy', $note))
            ->assertForbidden();

        $this->assertDatabaseHas('payroll_notes', ['id' => $note->id]);
    }

    private function makeNote(): PayrollNote
    {
        return PayrollNote::create([
            'payroll_period_id' => $this->period->id,
            'user_id' => $this->employee->id,
            'body' => 'Comentario original',
            'created_by' => $this->admin->id,
        ]);
    }
}
