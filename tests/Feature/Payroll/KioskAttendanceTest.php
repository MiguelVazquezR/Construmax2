<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'kiosk_pin' => '1234',
            'is_attendance_subject' => true,
        ]);
    }

    private function deviceHeaders(): array
    {
        return ['X-Attendance-Device' => $this->plainToken];
    }

    public function test_kiosk_page_renders_without_device_token(): void
    {
        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Kiosk/Index')
                ->has('punchTypes')
                ->has('faceRecognitionEnabled')
                ->has('pinFallbackEnabled')
            );
    }

    public function test_kiosk_exposes_the_pin_fallback_setting(): void
    {
        // The settings migration enables the pin fallback by default.
        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pinFallbackEnabled', true));

        PayrollSetting::current()->update(['kiosk_pin_fallback_enabled' => false]);

        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pinFallbackEnabled', false));
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

    public function test_punch_registers_the_attendance_log_with_pin_identification(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez')
            ->assertJsonPath('type_label', 'Entrada')
            ->assertJsonPath('suggested_next', AttendanceLog::TYPE_LUNCH_START);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'attendance_device_id' => $this->device->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
    }

    public function test_punch_rejects_a_wrong_pin(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '9999',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_punch_upgrades_a_legacy_pin_without_lookup(): void
    {
        // Pins stored before the lookup column existed have no fingerprint.
        DB::table('payroll_profiles')->where('user_id', $this->employee->id)->update(['kiosk_pin_lookup' => null]);

        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez');

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);

        // The fingerprint is stored on first use.
        $this->assertNotNull($this->employee->payrollProfile->fresh()->kiosk_pin_lookup);
    }

    public function test_punch_rejects_a_pin_shared_by_two_collaborators(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        PayrollProfile::create([
            'user_id' => $other->id,
            'employee_number' => 'EMP-0300',
            'kiosk_pin' => '1234',
            'is_attendance_subject' => true,
        ]);

        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_punch_rejects_duplicates_within_the_window(): void
    {
        $payload = [
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
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
            'kiosk_pin' => '5555',
            'is_attendance_subject' => false,
        ]);

        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '5555',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertStatus(422);

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_punch_requires_a_registered_device(): void
    {
        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ])->assertForbidden();
    }

    public function test_punch_is_rejected_after_the_termination_date(): void
    {
        Carbon::setTestNow('2026-09-20 09:00:00');

        $this->employee->payrollProfile->update(['termination_date' => '2026-09-19']);

        $this->postJson(route('attendance.kiosk.punch'), [
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
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
            'pin' => '1234',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertOk();

        $this->assertDatabaseCount('attendance_logs', 1);

        Carbon::setTestNow();
    }
}
