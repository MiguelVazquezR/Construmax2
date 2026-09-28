<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollProfile;
use App\Models\User;
use App\Models\VacationAdjustment;
use App\Services\Payroll\VacationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VacationAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.vacations.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage vacations']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.vacations.manage');

        $this->employee = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'hire_date' => '2026-01-01',
            'is_attendance_subject' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_registers_the_initial_balance(): void
    {
        Carbon::setTestNow('2026-02-01');

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_INITIAL,
                'days' => 10,
                'reason' => 'Saldo previo al uso del sistema',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $adjustment = VacationAdjustment::first();

        $this->assertNotNull($adjustment);
        $this->assertSame($this->employee->id, $adjustment->user_id);
        $this->assertSame(VacationAdjustment::TYPE_INITIAL, $adjustment->type);
        $this->assertSame(10.0, (float) $adjustment->days);
        $this->assertSame($this->admin->id, $adjustment->created_by);

        // 4 completed weeks of the first season (0.92) plus the initial balance.
        $balance = app(VacationService::class)->balanceFor($this->employee);
        $this->assertSame(10.92, $balance['available_days']);
    }

    public function test_manager_grants_days_and_registers_a_negative_adjustment(): void
    {
        Carbon::setTestNow('2026-02-01');

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_GRANT,
                'days' => 2.5,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_ADJUSTMENT,
                'days' => -0.5,
                'reason' => 'Corrección del saldo',
            ])
            ->assertSessionHasNoErrors();

        $balance = app(VacationService::class)->balanceFor($this->employee);

        $this->assertSame(2.0, $balance['adjustment_days']);
        $this->assertSame(2.92, $balance['available_days']);
    }

    public function test_manager_registers_historic_taken_days(): void
    {
        Carbon::setTestNow('2026-02-01');

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_TAKEN,
                'days' => 5,
                'reason' => 'Vacaciones tomadas antes de usar el sistema',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $adjustment = VacationAdjustment::first();

        // Captured as a positive amount but stored as a discount.
        $this->assertSame(-5.0, (float) $adjustment->days);
        $this->assertSame('Días tomados', $adjustment->typeLabel());

        $balance = app(VacationService::class)->balanceFor($this->employee);

        // 4 weeks accrued (0.92) minus the 5 taken days.
        $this->assertSame(5.0, $balance['manual_taken_days']);
        $this->assertSame(5.0, $balance['taken_days']);
        $this->assertSame(0.0, $balance['available_days']);
    }

    public function test_taken_days_accept_a_negative_amount_and_normalize_it(): void
    {
        Carbon::setTestNow('2026-02-01');

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_TAKEN,
                'days' => -3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(-3.0, (float) VacationAdjustment::first()->days);
    }

    public function test_negative_days_are_rejected_outside_of_a_manual_adjustment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_INITIAL,
                'days' => -3,
            ])
            ->assertSessionHasErrors('days');

        $this->assertDatabaseCount('vacation_adjustments', 0);
    }

    public function test_zero_days_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_GRANT,
                'days' => 0,
            ])
            ->assertSessionHasErrors('days');

        $this->assertDatabaseCount('vacation_adjustments', 0);
    }

    public function test_unknown_types_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => 'bonus',
                'days' => 2,
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('vacation_adjustments', 0);
    }

    public function test_users_without_the_manage_permission_cannot_register_movements(): void
    {
        $this->actingAs($this->employee)
            ->post(route('payroll.vacations.adjustments.store', $this->employee), [
                'type' => VacationAdjustment::TYPE_GRANT,
                'days' => 5,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('vacation_adjustments', 0);
    }

    public function test_manager_removes_a_movement_and_the_balance_is_recalculated(): void
    {
        Carbon::setTestNow('2026-02-01');

        $adjustment = VacationAdjustment::create([
            'user_id' => $this->employee->id,
            'type' => VacationAdjustment::TYPE_INITIAL,
            'days' => 10,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('payroll.vacations.adjustments.destroy', $adjustment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vacation_adjustments', ['id' => $adjustment->id]);

        $balance = app(VacationService::class)->balanceFor($this->employee);

        $this->assertSame(0.0, $balance['adjustment_days']);
        $this->assertSame(0.92, $balance['available_days']);
    }

    public function test_removing_a_movement_requires_the_manage_permission(): void
    {
        $adjustment = VacationAdjustment::create([
            'user_id' => $this->employee->id,
            'type' => VacationAdjustment::TYPE_GRANT,
            'days' => 3,
        ]);

        $this->actingAs($this->employee)
            ->delete(route('payroll.vacations.adjustments.destroy', $adjustment))
            ->assertForbidden();

        $this->assertDatabaseHas('vacation_adjustments', ['id' => $adjustment->id]);
    }
}
