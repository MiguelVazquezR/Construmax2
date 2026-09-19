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
                ->has('recentDays', 7)
                ->has('punches')
                ->has('vacationBalance')
                ->has('vacationRequests')
                ->has('payslips')
                ->has('punchTypes')
                ->has('faceRecognition')
                ->where('profile.can_remote_attendance', true)
            );
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
