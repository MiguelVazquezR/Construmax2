<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KioskAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceDevice $device;

    private string $plainToken = 'kiosk-test-token';

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = AttendanceDevice::create([
            'name' => 'Tablet pruebas',
            'token_hash' => hash('sha256', $this->plainToken),
            'registered_at' => now(),
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create(['name' => 'Juan Pérez', 'is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0100',
            'is_attendance_subject' => true,
        ]);
    }

    private function deviceHeaders(): array
    {
        return ['X-Attendance-Device' => $this->plainToken];
    }

    /**
     * Kiosk punches always store the device coordinates, so every valid request
     * must include them.
     */
    private function locationPayload(): array
    {
        return ['latitude' => 20.676, 'longitude' => -103.347, 'location_accuracy' => 12.5];
    }

    public function test_kiosk_page_renders_without_device_token(): void
    {
        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Kiosk/Index')
                ->has('punchTypes')
                ->has('faceRecognitionEnabled')
                ->has('employeeNumberEnabled')
            );
    }

    public function test_kiosk_exposes_the_employee_number_setting(): void
    {
        // The settings migration enables the employee-number method by default.
        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('employeeNumberEnabled', true));

        PayrollSetting::current()->update(['kiosk_employee_number_enabled' => false]);

        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('employeeNumberEnabled', false));
    }

    public function test_kiosk_page_exposes_the_device_registration_url(): void
    {
        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('registrationUrl', route('attendance.devices.register'))
            );
    }

    public function test_bootstrap_requires_a_valid_device_token(): void
    {
        $this->postJson(route('attendance.kiosk.bootstrap'))
            ->assertForbidden();

        $this->postJson(route('attendance.kiosk.bootstrap'), [], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('device.name', 'Tablet pruebas');
    }

    public function test_bootstrap_is_forbidden_for_a_revoked_device(): void
    {
        $this->device->update(['is_active' => false]);

        $this->postJson(route('attendance.kiosk.bootstrap'), [], $this->deviceHeaders())
            ->assertForbidden();
    }

    public function test_punch_registers_the_attendance_log_with_the_employee_number(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez')
            ->assertJsonPath('employee_number', 'EMP-0100')
            ->assertJsonPath('type_label', 'Entrada')
            ->assertJsonPath('suggested_next', AttendanceLog::TYPE_LUNCH_START);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'attendance_device_id' => $this->device->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_EMPLOYEE_NUMBER,
        ]);
    }

    public function test_punch_matches_the_employee_number_case_insensitively(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => ' emp-0100 ',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez');
    }

    public function test_punch_requires_the_device_location(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.latitude.0',
                'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.'
            );

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_the_punch_action_rejects_a_kiosk_punch_without_location(): void
    {
        // The rule lives in the action too, so a future kiosk caller cannot skip it.
        $this->expectException(ValidationException::class);

        app(RegisterAttendancePunchAction::class)->execute($this->employee, [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_EMPLOYEE_NUMBER,
        ], $this->device);
    }

    public function test_punch_stores_the_device_location(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'latitude' => 20.676,
            'longitude' => -103.347,
            'location_accuracy' => 12.5,
        ], $this->deviceHeaders())
            ->assertOk();

        $log = AttendanceLog::firstOrFail();

        $this->assertEquals(20.676, (float) $log->latitude);
        $this->assertEquals(-103.347, (float) $log->longitude);
        $this->assertEquals(12.5, (float) $log->location_accuracy);
    }

    public function test_punch_rejects_an_unknown_employee_number(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-9999',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_number');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_punch_rejects_duplicates_within_the_window(): void
    {
        $payload = [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ];

        $this->postJson(route('attendance.kiosk.punch'), $payload, $this->deviceHeaders())->assertOk();

        $this->postJson(route('attendance.kiosk.punch'), $payload, $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_punch_rejects_collaborators_without_attendance_enabled(): void
    {
        $other = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $other->id,
            'employee_number' => 'EMP-0200',
            'is_attendance_subject' => false,
        ]);

        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0200',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422);

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_kiosk_errors_are_returned_as_json_instead_of_the_error_page(): void
    {
        $this->postJson(route('attendance.kiosk.bootstrap'), [], ['X-Attendance-Device' => 'invalid-token'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Dispositivo no autorizado.');
    }

    public function test_punch_requires_a_registered_device(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ])->assertForbidden();
    }

    public function test_punch_is_rejected_after_the_termination_date(): void
    {
        Carbon::setTestNow('2026-09-20 09:00:00');

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-19']);

        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseCount('attendance_logs', 0);

        Carbon::setTestNow();
    }

    public function test_punch_is_still_allowed_on_the_termination_date(): void
    {
        Carbon::setTestNow('2026-09-19 09:00:00');

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-19']);

        $this->postJson(route('attendance.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertOk();

        $this->assertDatabaseCount('attendance_logs', 1);

        Carbon::setTestNow();
    }
}
