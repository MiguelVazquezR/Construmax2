<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceLog;
use App\Models\Incident;
use App\Models\PayrollAdjustment;
use App\Models\PayrollNote;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollPeriodControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.periods.index', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'View payroll periods']);
        Permission::create(['name' => 'payroll.periods.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage payroll periods']);
        Permission::create(['name' => 'payroll.periods.close', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Close payroll periods']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo(['payroll.periods.index', 'payroll.periods.manage', 'payroll.periods.close']);

        $this->employee = User::factory()->create(['is_active' => true, 'name' => 'Empleado Prueba']);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'hire_date' => '2024-01-01',
            'daily_salary' => 400,
            'daily_hours' => 8,
            'is_payroll_subject' => true,
            'is_attendance_subject' => true,
        ]);

        $shift = Shift::create([
            'name' => 'Matutino',
            'type' => 'fixed',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'meal_minutes' => 60,
            'days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        ShiftAssignment::create([
            'user_id' => $this->employee->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $shift->id,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);

        Carbon::setTestNow('2026-09-19');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function openPeriod(): PayrollPeriod
    {
        return PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_index_renders_for_managers(): void
    {
        $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Periods/Index')
                ->has('periods')
                ->has('typeLabels')
            );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->employee)
            ->get(route('payroll.periods.index'))
            ->assertForbidden();
    }

    public function test_store_creates_the_first_period(): void
    {
        PayrollSetting::current()->update([
            'period_type' => PayrollSetting::PERIOD_WEEKLY,
            'period_anchor_date' => '2026-09-07',
        ]);

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.store'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_periods', [
            'start_date' => '2026-09-14 00:00:00',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_store_is_blocked_when_a_period_is_already_open(): void
    {
        $this->openPeriod();

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.store'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_show_renders_the_live_pre_payroll_rows(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Periods/Show')
                ->has('rows', 1)
                ->has('stats')
                ->where('rows.0.name', 'Empleado Prueba')
            );
    }

    public function test_show_exposes_the_incidents_of_the_period(): void
    {
        $period = $this->openPeriod();

        Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_ABSENCE_JUSTIFIED,
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-08',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        // Outside the period: the drawer must not list it.
        Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_OTHER,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('incidents', 1)
                ->where('incidents.0.user_name', 'Empleado Prueba')
                ->where('incidents.0.type_label', 'Falta justificada')
                ->where('incidents.0.start_date', '2026-09-08')
            );
    }

    public function test_pre_payroll_shows_every_collaborator_with_totals_days_and_lines(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.pre-payroll', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Periods/PrePayroll')
                ->has('rows', 1)
                ->where('rows.0.user_name', 'Empleado Prueba')
                ->where('rows.0.lines.0.concept', 'Sueldo')
                ->has('rows.0.days', 7)
                ->where('rows.0.days.0.date', '2026-09-07')
                ->where('stats.employees', 1)
            );
    }

    public function test_pre_payroll_uses_the_frozen_payslips_of_a_closed_period(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.close', $period))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.pre-payroll', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Periods/PrePayroll')
                ->has('rows', 1)
                ->where('rows.0.user_name', 'Empleado Prueba')
                ->has('rows.0.lines')
                ->has('rows.0.days', 7)
            );
    }

    public function test_pre_payroll_requires_the_period_permission(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->employee)
            ->get(route('payroll.periods.pre-payroll', $period))
            ->assertForbidden();
    }

    public function test_days_endpoint_returns_the_detail_and_the_weekly_schedule(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.days', [$period, $this->employee]))
            ->assertOk()
            ->assertJsonStructure([
                'days' => [['date', 'status', 'punches']],
                'weekly_schedule' => [['weekday', 'label', 'workday']],
            ]);
    }

    public function test_days_endpoint_exposes_the_incident_type_key(): void
    {
        $period = $this->openPeriod();

        Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_VACATION,
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-08',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('payroll.periods.days', [$period, $this->employee]))
            ->assertOk()
            ->json();

        $day = collect($response['days'])->firstWhere('date', '2026-09-08');

        $this->assertSame('vacation', $day['incident_type_key']);
        $this->assertSame('Vacaciones', $day['incident_type']);
    }

    public function test_show_exposes_the_comments_of_the_collaborators(): void
    {
        $period = $this->openPeriod();

        PayrollNote::create([
            'payroll_period_id' => $period->id,
            'user_id' => $this->employee->id,
            'body' => 'Se le descontará el préstamo en dos pagos.',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notes', 1)
                ->where('notes.0.user_id', $this->employee->id)
                ->where('notes.0.body', 'Se le descontará el préstamo en dos pagos.')
            );
    }

    public function test_pre_payroll_exposes_the_comments_of_the_period(): void
    {
        $period = $this->openPeriod();

        PayrollNote::create([
            'payroll_period_id' => $period->id,
            'user_id' => $this->employee->id,
            'body' => 'Comentario que viaja a la pre-nómina.',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.pre-payroll', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notes', 1)
                ->where('notes.0.body', 'Comentario que viaja a la pre-nómina.')
            );
    }

    public function test_pre_payroll_exposes_the_incidents_of_the_period(): void
    {
        $period = $this->openPeriod();

        Incident::create([
            'user_id' => $this->employee->id,
            'type' => Incident::TYPE_ABSENCE_UNJUSTIFIED,
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-09',
            'days' => 2,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.pre-payroll', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('incidents', 1)
                ->where('incidents.0.user_name', 'Empleado Prueba')
                ->where('incidents.0.type_label', 'Falta injustificada')
                ->where('incidents.0.start_date', '2026-09-08')
                ->where('incidents.0.end_date', '2026-09-09')
            );
    }

    public function test_show_hides_collaborators_terminated_before_the_period(): void
    {
        $period = $this->openPeriod();

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-06']);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 0)
                ->where('stats.employees', 0)
            );
    }

    public function test_show_keeps_a_collaborator_terminated_inside_the_period(): void
    {
        $period = $this->openPeriod();

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-09']);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.show', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.termination_date', '2026-09-09')
                ->where('rows.0.unpaid_days', 3)
            );
    }

    public function test_pre_payroll_hides_collaborators_terminated_before_the_period(): void
    {
        $period = $this->openPeriod();

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-06']);

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.pre-payroll', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('rows', 0));
    }

    public function test_days_endpoint_filters_by_the_requested_date_range(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.days', [
                'period' => $period->id,
                'user' => $this->employee->id,
                'from' => '2026-09-08',
                'to' => '2026-09-10',
            ]))
            ->assertOk()
            ->assertJsonCount(3, 'days')
            ->assertJsonPath('days.0.date', '2026-09-08')
            ->assertJsonPath('days.2.date', '2026-09-10')
            ->assertJsonPath('range.from', '2026-09-08')
            ->assertJsonPath('range.to', '2026-09-10');
    }

    public function test_days_endpoint_clamps_the_range_and_only_returns_the_punches_inside_it(): void
    {
        $period = $this->openPeriod();

        foreach ([['2026-09-09 08:00:00', AttendanceLog::TYPE_CHECK_IN], ['2026-09-11 18:00:00', AttendanceLog::TYPE_CHECK_OUT]] as [$punchedAt, $type]) {
            AttendanceLog::create([
                'user_id' => $this->employee->id,
                'type' => $type,
                'punched_at' => $punchedAt,
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get(route('payroll.periods.days', [
                'period' => $period->id,
                'user' => $this->employee->id,
                'from' => '2026-01-01',
                'to' => '2026-09-09',
            ]))
            ->assertOk();

        // The range can never go outside the period.
        $response->assertJsonPath('range.from', '2026-09-07')
            ->assertJsonPath('range.to', '2026-09-09')
            ->assertJsonCount(3, 'days');

        $days = collect($response->json('days'));

        $this->assertCount(1, $days->firstWhere('date', '2026-09-09')['punches']);
        $this->assertNull($days->firstWhere('date', '2026-09-11'));
    }

    public function test_days_endpoint_falls_back_to_the_whole_period_with_an_invalid_range(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.days', [
                'period' => $period->id,
                'user' => $this->employee->id,
                'from' => 'no-es-fecha',
                'to' => '2026-09-10',
            ]))
            ->assertOk()
            ->assertJsonCount(4, 'days')
            ->assertJsonPath('days.0.date', '2026-09-07')
            ->assertJsonPath('range.from', '2026-09-07')
            ->assertJsonPath('range.to', '2026-09-10');
    }

    public function test_manual_punch_is_rejected_after_the_termination_date(): void
    {
        $period = $this->openPeriod();

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-08']);

        $this->actingAs($this->admin)
            ->post(route('payroll.attendance-logs.store'), [
                'user_id' => $this->employee->id,
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'punched_at' => '2026-09-10 08:00:00',
                'edit_reason' => 'Olvidó registrar su entrada',
            ])
            ->assertSessionHasErrors('punched_at');

        $this->assertDatabaseCount('attendance_logs', 0);

        // Corrections inside the employment window keep working.
        $this->actingAs($this->admin)
            ->post(route('payroll.attendance-logs.store'), [
                'user_id' => $this->employee->id,
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'punched_at' => '2026-09-08 08:00:00',
                'edit_reason' => 'Olvidó registrar su entrada',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_override_ignores_a_late_arrival(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->put(route('payroll.periods.override', [$period, $this->employee]), [
                'date' => '2026-09-07',
                'late_ignored' => true,
                'notes' => 'Tráfico',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_day_overrides', [
            'user_id' => $this->employee->id,
            'late_ignored' => true,
            'notes' => 'Tráfico',
        ]);
    }

    public function test_attendance_log_update_is_audited(): void
    {
        $log = AttendanceLog::create([
            'user_id' => $this->employee->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'punched_at' => '2026-09-07 08:15:00',
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);

        $this->actingAs($this->admin)
            ->put(route('payroll.attendance-logs.update', $log), [
                'punched_at' => '2026-09-07 08:05:00',
                'edit_reason' => 'Corrección por error de captura',
            ])
            ->assertRedirect();

        $log->refresh();

        $this->assertSame('08:05:00', $log->punched_at->format('H:i:s'));
        $this->assertSame($this->admin->id, $log->edited_by);
        $this->assertSame('Corrección por error de captura', $log->edit_reason);
        $this->assertNotNull($log->edited_at);
    }

    public function test_attendance_log_update_requires_the_manage_permission(): void
    {
        $log = AttendanceLog::create([
            'user_id' => $this->employee->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'punched_at' => '2026-09-07 08:15:00',
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);

        $this->actingAs($this->employee)
            ->put(route('payroll.attendance-logs.update', $log), [
                'punched_at' => '2026-09-07 08:00:00',
                'edit_reason' => 'Intento sin permiso',
            ])
            ->assertForbidden();
    }

    public function test_adjustments_can_be_added_and_removed_on_an_open_period(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.adjustments.store', $period), [
                'user_id' => $this->employee->id,
                'type' => PayrollAdjustment::TYPE_EARNING,
                'concept' => 'Bono',
                'amount' => 300,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $adjustment = PayrollAdjustment::first();

        $this->assertNotNull($adjustment);

        $this->actingAs($this->admin)
            ->delete(route('payroll.adjustments.destroy', $adjustment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('payroll_adjustments', ['id' => $adjustment->id]);
    }

    public function test_adjustments_are_blocked_on_a_closed_period(): void
    {
        $period = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-08-31',
            'end_date' => '2026-09-06',
            'status' => PayrollPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.adjustments.store', $period), [
                'user_id' => $this->employee->id,
                'type' => PayrollAdjustment::TYPE_EARNING,
                'concept' => 'Bono',
                'amount' => 300,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_close_and_reopen_endpoints(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.close', $period))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $period->fresh()->status);
        $this->assertSame(1, $period->payslips()->count());

        $this->actingAs($this->admin)
            ->post(route('payroll.periods.reopen', $period))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PayrollPeriod::STATUS_OPEN, $period->fresh()->status);
    }

    public function test_export_downloads_the_report(): void
    {
        $period = $this->openPeriod();

        $this->actingAs($this->admin)
            ->get(route('payroll.periods.export', $period))
            ->assertDownload('nomina_2026-09-07.xlsx');
    }
}
