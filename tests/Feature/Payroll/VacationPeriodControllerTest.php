<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollProfile;
use App\Models\User;
use App\Models\VacationPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VacationPeriodControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create([
            'name' => 'payroll.vacations.manage',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'Manage vacations',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.vacations.manage');

        $this->employee = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'hire_date' => '2024-08-05',
            'is_attendance_subject' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'year_number' => 6,
            'start_date' => '2029-08-05',
            'end_date' => '2030-08-04',
            'entitled_days' => 22,
            'accrued_days' => 0,
            'taken_days' => 0,
            'premium_paid' => false,
            'premium_paid_at' => null,
        ], $overrides);
    }

    /**
     * Visiting the vacations screen stores the calculated periods.
     */
    private function visitBalance(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payroll.vacations.index', ['user_id' => $this->employee->id]))
            ->assertOk();
    }

    public function test_the_calculated_periods_are_stored_and_listed(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();

        // Two years of service started: Ago 2024–Ago 2025 and Ago 2025–Ago 2026.
        $this->assertDatabaseCount('vacation_periods', 2);

        $this->actingAs($this->admin)
            ->get(route('payroll.vacations.index', ['user_id' => $this->employee->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Vacations/Index')
                ->has('periods', 2)
                ->where('periods.0.year_number', 1)
                ->where('periods.0.is_completed', true)
                ->where('periods.1.year_number', 2)
                ->where('periods.1.is_completed', false)
            );
    }

    public function test_manager_adds_a_manual_period(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.periods.store', $this->employee), $this->validPayload([
                'premium_paid' => true,
                'premium_paid_at' => '2026-02-10',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $period = VacationPeriod::query()->where('year_number', 6)->first();

        $this->assertNotNull($period);
        $this->assertTrue($period->is_customized);
        $this->assertSame($this->employee->id, $period->user_id);
        $this->assertSame($this->admin->id, $period->created_by);
        $this->assertSame('2026-02-10', $period->premium_paid_at->toDateString());
        $this->assertSame(22.0, (float) $period->entitled_days);
    }

    public function test_a_duplicated_year_is_rejected(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.periods.store', $this->employee), $this->validPayload([
                'year_number' => 2,
                'start_date' => '2025-08-05',
                'end_date' => '2026-08-04',
            ]))
            ->assertSessionHasErrors('year_number');
    }

    public function test_manager_marks_the_premium_as_paid(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();
        $period = VacationPeriod::query()->where('year_number', 1)->first();

        $this->actingAs($this->admin)
            ->put(route('payroll.vacations.periods.update', $period), $this->validPayload([
                'year_number' => 1,
                'start_date' => '2024-08-05',
                'end_date' => '2025-08-04',
                'entitled_days' => 12,
                'accrued_days' => 12,
                'taken_days' => 0,
                'premium_paid' => true,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $period->refresh();

        // The payment defaults to today when no date is provided.
        $this->assertSame('2026-02-15', $period->premium_paid_at->toDateString());
        // Marking the premium does not freeze the calculated days.
        $this->assertFalse($period->is_customized);

        // A second visit keeps the payment and the auto-refreshing days.
        $this->visitBalance();

        $this->assertSame('2026-02-15', $period->refresh()->premium_paid_at->toDateString());
        $this->assertFalse($period->is_customized);
    }

    public function test_customized_days_are_not_overwritten_by_the_sync(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();
        $period = VacationPeriod::query()->where('year_number', 2)->first();

        $this->actingAs($this->admin)
            ->put(route('payroll.vacations.periods.update', $period), $this->validPayload([
                'year_number' => 2,
                'start_date' => '2025-08-05',
                'end_date' => '2026-08-04',
                'entitled_days' => 14,
                'accrued_days' => 12.5,
                'taken_days' => 3,
            ]))
            ->assertSessionHasNoErrors();

        // A second visit runs the sync again and must keep the manual values.
        $this->visitBalance();

        $period->refresh();

        $this->assertSame(12.5, (float) $period->accrued_days);
        $this->assertSame(3.0, (float) $period->taken_days);
    }

    public function test_a_deleted_period_is_not_generated_again(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();
        $period = VacationPeriod::query()->where('year_number', 1)->first();

        $this->actingAs($this->admin)
            ->delete(route('payroll.vacations.periods.destroy', $period))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('vacation_periods', ['id' => $period->id]);

        $this->visitBalance();

        $this->assertSame(1, VacationPeriod::query()->forUser($this->employee->id)->count());
        $this->assertSame(2, VacationPeriod::withTrashed()->forUser($this->employee->id)->count());
    }

    public function test_adding_a_deleted_year_again_restores_the_row(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();
        $period = VacationPeriod::query()->where('year_number', 2)->first();
        $period->delete();

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.periods.store', $this->employee), $this->validPayload([
                'year_number' => 2,
                'start_date' => '2025-08-05',
                'end_date' => '2026-08-04',
                'entitled_days' => 14,
                'accrued_days' => 10,
                'taken_days' => 2,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, VacationPeriod::query()->forUser($this->employee->id)->count());

        $restored = VacationPeriod::query()->where('year_number', 2)->first();

        $this->assertSame(10.0, (float) $restored->accrued_days);
        $this->assertTrue($restored->is_customized);
    }

    public function test_users_without_the_manage_permission_cannot_touch_the_periods(): void
    {
        Carbon::setTestNow('2026-02-15');

        $this->visitBalance();
        $period = VacationPeriod::query()->where('year_number', 1)->first();

        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('payroll.vacations.periods.store', $this->employee), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('payroll.vacations.periods.update', $period), $this->validPayload(['year_number' => 1]))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('payroll.vacations.periods.destroy', $period))
            ->assertForbidden();

        $this->assertFalse($period->refresh()->is_customized);
    }
}
