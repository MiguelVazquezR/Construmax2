<?php

namespace Tests\Feature\Payroll;

use App\Models\Incident;
use App\Models\PayrollProfile;
use App\Models\User;
use App\Models\VacationRequest;
use App\Notifications\VacationRequested;
use App\Notifications\VacationReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VacationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.vacations.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage vacations']);
        Permission::create(['name' => 'payroll.vacations.approve', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Approve vacations']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo(['payroll.vacations.manage', 'payroll.vacations.approve']);

        $this->employee = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'hire_date' => '2026-01-01',
            'is_attendance_subject' => true,
        ]);

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_renders_for_managers(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payroll.vacations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Vacations/Index')
                ->has('requests')
                ->has('users')
                ->has('statuses')
            );
    }

    public function test_index_is_forbidden_for_a_plain_employee(): void
    {
        $this->actingAs($this->employee)
            ->get(route('payroll.vacations.index'))
            ->assertForbidden();
    }

    public function test_payroll_subjects_without_attendance_appear_in_the_vacation_list(): void
    {
        $payrollOnly = User::factory()->create(['is_active' => true, 'name' => 'Solo Nómina']);

        PayrollProfile::create([
            'user_id' => $payrollOnly->id,
            'hire_date' => '2025-01-01',
            'is_payroll_subject' => true,
            'is_attendance_subject' => false,
        ]);

        Carbon::setTestNow('2026-07-01');

        // A manager can register the request on their behalf...
        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.requests.store'), [
                'user_id' => $payrollOnly->id,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-18',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($payrollOnly->id, VacationRequest::first()->user_id);

        // ...and they are listed in the collaborator selector of the screen.
        $this->actingAs($this->admin)
            ->get(route('payroll.vacations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('name')->contains('Solo Nómina'))
            );
    }

    public function test_a_payroll_subject_requests_their_own_vacation_without_attendance(): void
    {
        Carbon::setTestNow('2026-07-01');

        $payrollOnly = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $payrollOnly->id,
            'hire_date' => '2025-01-01',
            'is_payroll_subject' => true,
            'is_attendance_subject' => false,
        ]);

        $this->actingAs($payrollOnly)
            ->post(route('payroll.vacations.requests.store'), [
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-18',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($payrollOnly->id, VacationRequest::first()->user_id);
    }

    public function test_employee_requests_their_own_vacation(): void
    {
        Carbon::setTestNow('2026-07-01');

        $this->actingAs($this->employee)
            ->post(route('payroll.vacations.requests.store'), [
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-18',
                'reason' => 'Viaje familiar',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $request = VacationRequest::first();

        $this->assertNotNull($request);
        $this->assertSame($this->employee->id, $request->user_id);
        $this->assertSame(VacationRequest::STATUS_PENDING, $request->status);
        $this->assertSame(5.0, (float) $request->days);
        $this->assertSame($this->employee->id, $request->requested_by);

        Notification::assertSentTo($this->admin, VacationRequested::class);
    }

    public function test_request_is_rejected_without_available_balance(): void
    {
        Carbon::setTestNow('2026-01-05');

        $this->actingAs($this->employee)
            ->post(route('payroll.vacations.requests.store'), [
                'start_date' => '2026-01-05',
                'end_date' => '2026-01-06',
            ])
            ->assertSessionHasErrors('balance');
    }

    public function test_admin_approves_and_the_incident_is_created(): void
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'days' => 2,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.approve', $request), ['notes' => 'Aprobada por el supervisor'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $request->refresh();

        $this->assertSame(VacationRequest::STATUS_APPROVED, $request->status);
        $this->assertSame($this->admin->id, $request->reviewed_by);
        $this->assertSame('Aprobada por el supervisor', $request->review_notes);

        $this->assertDatabaseHas('incidents', [
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_VACATION,
            'vacation_request_id' => $request->id,
            'status' => Incident::STATUS_APPROVED,
        ]);

        Notification::assertSentTo($this->employee, VacationReviewed::class);
    }

    public function test_admin_rejects_without_creating_an_incident(): void
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'days' => 2,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('payroll.vacations.reject', $request), ['notes' => 'Periodo de cierre'])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(VacationRequest::STATUS_REJECTED, $request->status);
        $this->assertDatabaseCount('incidents', 0);
        Notification::assertSentTo($this->employee, VacationReviewed::class);
    }

    public function test_approve_requires_the_permission(): void
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'days' => 2,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->post(route('payroll.vacations.approve', $request))
            ->assertForbidden();
    }

    public function test_employee_cancels_their_own_pending_request(): void
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'days' => 2,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->delete(route('payroll.vacations.requests.cancel', $request))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(VacationRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    public function test_employee_cannot_cancel_someone_elses_request(): void
    {
        $other = User::factory()->create(['is_active' => true]);

        $request = VacationRequest::create([
            'user_id' => $other->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'days' => 2,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->delete(route('payroll.vacations.requests.cancel', $request))
            ->assertForbidden();
    }

    public function test_balance_endpoint_returns_the_own_balance(): void
    {
        Carbon::setTestNow('2026-07-01');

        $this->actingAs($this->employee)
            ->getJson(route('payroll.vacations.balance'))
            ->assertOk()
            ->assertJsonPath('balance.hire_date', '2026-01-01')
            ->assertJsonPath('balance.current_season', 1);
    }
}
