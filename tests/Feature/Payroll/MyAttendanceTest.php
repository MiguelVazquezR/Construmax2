<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceLog;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create(['name' => 'Luis Torres', 'is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0300',
            'hire_date' => '2024-01-15',
            'is_attendance_subject' => true,
            'can_remote_attendance' => true,
        ]);

        PayrollSetting::current()->update(['remote_geolocation_required' => true]);
    }

    public function test_my_attendance_page_is_forbidden_without_an_attendance_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('payroll.my-attendance.index'))
            ->assertForbidden();
    }

    public function test_my_attendance_page_renders_the_portal(): void
    {
        $this->actingAs($this->employee)
            ->get(route('payroll.my-attendance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/MyAttendance/Index')
                ->has('today')
                ->has('today.punches')
                ->has('periodDays', 7)
                ->has('periodDays.0.punches')
                ->has('historyRange')
                ->has('vacationBalance')
                ->has('vacationMovements')
                ->has('vacationPeriods')
                ->has('vacationRequests')
                ->has('payslips')
                ->has('punchTypes')
                ->has('faceRecognition')
                ->has('remotePinEnabled')
                ->where('profile.can_remote_attendance', true)
                ->where('profile.has_kiosk_pin', false)
            );
    }

    public function test_history_covers_the_open_period_that_contains_today(): void
    {
        $period = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => Carbon::today()->subDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($this->employee)
            ->get(route('payroll.my-attendance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('periodDays', 7)
                ->where('historyRange.start_date', $period->start_date->toDateString())
                ->where('historyRange.end_date', $period->end_date->toDateString())
            );
    }

    public function test_history_ignores_a_next_period_opened_in_advance(): void
    {
        // The payroll team closed the current week early and opened the next
        // one: the history keeps showing the dates that contain today until
        // the weekly rollover.
        PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(8)->toDateString(),
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $weekStart = Carbon::today()->startOfWeek();

        $this->actingAs($this->employee)
            ->get(route('payroll.my-attendance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('periodDays', 7)
                ->where('historyRange.start_date', $weekStart->toDateString())
                ->where('historyRange.end_date', $weekStart->copy()->addDays(6)->toDateString())
            );
    }

    public function test_vacation_preview_returns_the_working_days_of_the_range(): void
    {
        $this->actingAs($this->employee)
            ->getJson(route('payroll.my-attendance.vacation-preview', [
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-09',
            ]))
            ->assertOk()
            ->assertJsonPath('days', 5)
            ->assertJsonStructure(['days', 'available_days', 'minimum_days', 'exceeds_balance']);
    }

    public function test_vacation_preview_requires_an_attendance_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('payroll.my-attendance.vacation-preview', [
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-09',
            ]))
            ->assertForbidden();
    }

    public function test_remote_punch_is_forbidden_without_an_attendance_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
            ])
            ->assertForbidden();
    }

    public function test_remote_punch_requires_the_remote_attendance_flag(): void
    {
        $this->employee->payrollProfile->update(['can_remote_attendance' => false]);

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_remote_punch_requires_geolocation_when_configured(): void
    {
        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('location');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_remote_punch_registers_the_log_with_geolocation(): void
    {
        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.6736,
                'longitude' => -103.3445,
                'location_accuracy' => 12.5,
            ])
            ->assertOk()
            ->assertJsonPath('type_label', 'Entrada')
            ->assertJsonPath('suggested_next', AttendanceLog::TYPE_LUNCH_START);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_REMOTE,
            'identifier_method' => AttendanceLog::IDENTIFIER_MANUAL,
        ]);

        $log = AttendanceLog::first();
        $this->assertSame(20.6736, $log->latitude);
        $this->assertSame(-103.3445, $log->longitude);
    }

    public function test_remote_punch_is_rejected_after_the_termination_date(): void
    {
        Carbon::setTestNow('2026-09-20 09:00:00');

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-18']);

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseCount('attendance_logs', 0);

        Carbon::setTestNow();
    }

    public function test_portal_marks_a_dismissed_collaborator_as_unable_to_punch(): void
    {
        Carbon::setTestNow('2026-09-20 09:00:00');

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-18']);

        $this->actingAs($this->employee)
            ->get(route('payroll.my-attendance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profile.can_punch', false)
                ->where('profile.termination_date', '2026-09-18')
            );

        Carbon::setTestNow();
    }

    public function test_remote_punch_verifies_the_face_when_recognition_is_enabled(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $other = User::factory()->create(['is_active' => true]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($other) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->once()->andReturn([
                'external_image_id' => (string) $other->id,
                'face_id' => 'face-x',
                'similarity' => 99.0,
            ]);
        });

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'photo' => 'data:image/jpeg;base64,/9j/AAAA',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_remote_punch_accepts_the_own_face(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->once()->andReturn([
                'external_image_id' => (string) $this->employee->id,
                'face_id' => 'face-y',
                'similarity' => 97.25,
            ]);
        });

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'photo' => 'data:image/jpeg;base64,/9j/BBBB',
            ])
            ->assertOk()
            ->assertJsonPath('similarity', 97.25);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_REMOTE,
            'identifier_method' => AttendanceLog::IDENTIFIER_FACE,
        ]);
    }

    public function test_remote_punch_accepts_the_kiosk_pin_as_identification(): void
    {
        $this->employee->payrollProfile->update(['kiosk_pin' => '2468']);

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'method' => 'pin',
                'pin' => '2468',
            ])
            ->assertOk()
            ->assertJsonPath('identifier_method', AttendanceLog::IDENTIFIER_PIN)
            ->assertJsonPath('similarity', null);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_REMOTE,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
    }

    public function test_remote_punch_with_a_wrong_pin_is_rejected(): void
    {
        $this->employee->payrollProfile->update(['kiosk_pin' => '2468']);

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'method' => 'pin',
                'pin' => '9999',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_remote_punch_with_pin_is_rejected_when_the_fallback_is_disabled(): void
    {
        PayrollSetting::current()->update(['kiosk_pin_fallback_enabled' => false]);
        $this->employee->payrollProfile->update(['kiosk_pin' => '2468']);

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'method' => 'pin',
                'pin' => '2468',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_remote_punch_with_pin_bypasses_the_face_verification(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);
        $this->employee->payrollProfile->update(['kiosk_pin' => '2468']);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('search');
        });

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'method' => 'pin',
                'pin' => '2468',
            ])
            ->assertOk()
            ->assertJsonPath('identifier_method', AttendanceLog::IDENTIFIER_PIN);
    }

    public function test_remote_punch_with_face_method_is_rejected_when_recognition_is_off(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => false]);

        $this->actingAs($this->employee)
            ->postJson(route('payroll.my-attendance.punch'), [
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'latitude' => 20.67,
                'longitude' => -103.34,
                'method' => 'face',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('method');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_collaborator_can_print_their_own_payslip(): void
    {
        [$period] = $this->closedPeriodWithPayslip();

        $this->actingAs($this->employee)
            ->get(route('payroll.periods.payslips.print', [
                'period' => $period->id,
                'users' => [$this->employee->id],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Payslips/Print')
                ->has('payslips', 1)
                ->where('payslips.0.user_name', 'Luis Torres')
            );
    }

    public function test_collaborator_cannot_print_payslips_of_other_users(): void
    {
        [$period] = $this->closedPeriodWithPayslip();
        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->employee)
            ->get(route('payroll.periods.payslips.print', [
                'period' => $period->id,
                'users' => [$other->id],
            ]))
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->get(route('payroll.periods.payslips.print', ['period' => $period->id]))
            ->assertForbidden();
    }

    /**
     * @return array{0: PayrollPeriod}
     */
    private function closedPeriodWithPayslip(): array
    {
        $period = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
            'status' => PayrollPeriod::STATUS_CLOSED,
            'closed_at' => now(),
            'total_gross' => 2000,
            'total_deductions' => 0,
            'total_net' => 2000,
        ]);

        Payslip::create([
            'payroll_period_id' => $period->id,
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0300',
            'daily_salary' => 400,
            'days_paid' => 5,
            'total_gross' => 2000,
            'total_deductions' => 0,
            'total_net' => 2000,
            'generated_at' => now(),
        ]);

        return [$period];
    }
}
