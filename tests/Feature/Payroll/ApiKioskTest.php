<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * JSON contract consumed by the Android attendance kiosk app.
 * See docs/kiosk-android-app.md.
 */
class ApiKioskTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceDevice $device;

    private string $plainToken = 'android-kiosk-token';

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = AttendanceDevice::create([
            'name' => 'Tablet entrada',
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

    private function registeredDeviceHeaders(string $token): array
    {
        return ['X-Attendance-Device' => $token];
    }

    /**
     * Kiosk punches always store the device coordinates, so every valid request
     * must include them.
     */
    private function locationPayload(): array
    {
        return ['latitude' => 20.676, 'longitude' => -103.347, 'location_accuracy' => 12.5];
    }

    private function configureRegistrationPassword(string $password = 'kiosco-2026'): void
    {
        PayrollSetting::current()->update(['device_registration_password' => $password]);
    }

    public function test_ping_answers_ok_for_an_authorized_device(): void
    {
        $this->getJson(route('api.kiosk.ping'), $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('device.id', $this->device->id)
            ->assertJsonPath('device.name', 'Tablet entrada')
            ->assertJsonStructure(['status', 'device' => ['id', 'name', 'location'], 'server_time']);
    }

    public function test_ping_answers_ok_without_a_device_token(): void
    {
        $this->getJson(route('api.kiosk.ping'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('device', null);
    }

    public function test_ping_ignores_a_revoked_device_token(): void
    {
        $this->device->update(['is_active' => false]);

        $this->getJson(route('api.kiosk.ping'), $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('device', null);
    }

    public function test_bootstrap_returns_the_device_punch_types_and_settings(): void
    {
        $this->postJson(route('api.kiosk.bootstrap'), [], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('device.name', 'Tablet entrada')
            ->assertJsonPath('employeeNumberEnabled', true)
            ->assertJsonPath('faceRecognitionEnabled', false)
            ->assertJsonPath('recoveryPasswordConfigured', false)
            ->assertJsonPath('registrationUrl', route('attendance.devices.register'))
            ->assertJsonStructure([
                'device' => ['id', 'name', 'location'],
                'punchTypes',
                'server_time',
                'timezone',
                'appName',
            ]);
    }

    public function test_bootstrap_works_without_a_device_token(): void
    {
        $this->postJson(route('api.kiosk.bootstrap'))
            ->assertOk()
            ->assertJsonPath('device', null)
            ->assertJsonPath('employeeNumberEnabled', true)
            ->assertJsonStructure(['punchTypes', 'server_time', 'timezone', 'appName']);
    }

    public function test_bootstrap_registers_the_app_device_from_its_android_id(): void
    {
        $this->postJson(route('api.kiosk.bootstrap'), [
            'device_key' => 'android-id-almacen',
            'device_name' => 'SM-T500',
        ])
            ->assertOk()
            ->assertJsonPath('device.name', 'Tablet SM-T500')
            ->assertJsonPath('device.location', null);

        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Tablet SM-T500',
            'is_active' => true,
        ]);

        // A second load reuses the row instead of duplicating the tablet.
        $this->postJson(route('api.kiosk.bootstrap'), [
            'device_key' => 'android-id-almacen',
            'device_name' => 'SM-T500',
        ])->assertOk();

        $this->assertSame(2, AttendanceDevice::query()->count());
    }

    public function test_punch_registers_the_attendance_log_with_the_employee_number(): void
    {
        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'emp-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez')
            ->assertJsonPath('employee_number', 'EMP-0100')
            ->assertJsonPath('type', AttendanceLog::TYPE_CHECK_IN)
            ->assertJsonPath('type_label', 'Entrada')
            ->assertJsonPath('suggested_next', AttendanceLog::TYPE_LUNCH_START)
            ->assertJsonPath('suggested_next_label', 'Inicio de comida');

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'attendance_device_id' => $this->device->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_EMPLOYEE_NUMBER,
        ]);
    }

    public function test_punch_rejects_an_unknown_employee_number(): void
    {
        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'EMP-9999',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_number');
    }

    public function test_punch_rejects_a_request_without_location(): void
    {
        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_punch_rejects_an_unknown_punch_type(): void
    {
        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => 'unknown_type',
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_punch_works_without_a_device_token(): void
    {
        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ])
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez');

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'attendance_device_id' => null,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_KIOSK,
        ]);
    }

    public function test_punch_links_the_log_to_the_device_of_the_app(): void
    {
        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'device_key' => 'android-id-entrada',
            'device_name' => 'Lenovo TB-X306F',
            ...$this->locationPayload(),
        ])->assertOk();

        $device = AttendanceDevice::query()->where('name', 'Tablet Lenovo TB-X306F')->first();

        $this->assertNotNull($device);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ]);
    }

    public function test_punch_is_not_blocked_by_the_revoked_device_token(): void
    {
        $this->device->update(['is_active' => false]);

        $this->postJson(route('api.kiosk.punch'), [
            'employee_number' => 'EMP-0100',
            'type' => AttendanceLog::TYPE_CHECK_IN,
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('type', AttendanceLog::TYPE_CHECK_IN);
    }

    public function test_face_punch_registers_the_attendance_log_with_face_identification(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->once()->andReturn([
                'external_image_id' => (string) $this->employee->id,
                'face_id' => 'aws-face-1',
                'similarity' => 98.42,
            ]);
        });

        $this->postJson(route('api.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,'.base64_encode('fake-photo'),
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Juan Pérez')
            ->assertJsonPath('similarity', 98.42)
            ->assertJsonPath('suggested_next', AttendanceLog::TYPE_LUNCH_START);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'identifier_method' => AttendanceLog::IDENTIFIER_FACE,
            'source' => AttendanceLog::SOURCE_KIOSK,
        ]);
    }

    public function test_face_punch_reports_when_no_face_matches(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->once()->andReturn(null);
        });

        $this->postJson(route('api.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,'.base64_encode('fake-photo'),
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('face');
    }

    public function test_face_punch_is_rejected_when_the_provider_is_not_configured(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->postJson(route('api.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,'.base64_encode('fake-photo'),
            ...$this->locationPayload(),
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('face');
    }

    public function test_recover_asks_for_the_password_when_the_device_is_unknown(): void
    {
        $this->configureRegistrationPassword();

        $this->postJson(route('api.kiosk.recover'), [
            'device_key' => 'unknown-android-id',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'password_required')
            ->assertJsonPath('device', null);
    }

    public function test_recover_reports_when_recognition_is_disabled(): void
    {
        $this->postJson(route('api.kiosk.recover'), [
            'device_key' => 'unknown-android-id',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('device', null);
    }

    public function test_recover_authorizes_the_device_with_the_registration_password(): void
    {
        $this->configureRegistrationPassword();

        $response = $this->postJson(route('api.kiosk.recover'), [
            'device_key' => 'android-id-after-clear-data',
            'password' => 'kiosco-2026',
            'device_id' => $this->device->id,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'authorized')
            ->assertJsonPath('device.id', $this->device->id);

        $token = $response->json('token');

        $this->assertIsString($token);
        $this->getJson(route('api.kiosk.ping'), $this->registeredDeviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('device.id', $this->device->id);
    }

    public function test_recover_rejects_a_wrong_registration_password(): void
    {
        $this->configureRegistrationPassword();

        $this->postJson(route('api.kiosk.recover'), [
            'device_key' => 'unknown-android-id',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_device_registers_itself_with_the_registration_password(): void
    {
        $this->configureRegistrationPassword();

        $response = $this->postJson(route('api.kiosk.devices.register'), [
            'name' => 'Tablet almacén',
            'location' => 'Almacén',
            'password' => 'kiosco-2026',
            'device_key' => 'android-id-almacen',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'registered')
            ->assertJsonPath('device.name', 'Tablet almacén');

        $token = $response->json('token');

        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Tablet almacén',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        $this->getJson(route('api.kiosk.ping'), $this->registeredDeviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('device.name', 'Tablet almacén');
    }

    public function test_device_registration_is_rejected_when_the_password_is_not_configured(): void
    {
        $this->postJson(route('api.kiosk.devices.register'), [
            'name' => 'Tablet almacén',
            'password' => 'kiosco-2026',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_device_registration_rejects_a_wrong_password(): void
    {
        $this->configureRegistrationPassword();

        $this->postJson(route('api.kiosk.devices.register'), [
            'name' => 'Tablet almacén',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('attendance_devices', ['name' => 'Tablet almacén']);
    }

    public function test_device_registration_reports_an_already_authorized_device(): void
    {
        $this->configureRegistrationPassword();

        $this->postJson(route('api.kiosk.devices.register'), [
            'name' => 'Tablet entrada',
            'password' => 'kiosco-2026',
        ], $this->deviceHeaders())
            ->assertStatus(409)
            ->assertJsonPath('status', 'already_authorized')
            ->assertJsonPath('device.id', $this->device->id);
    }
}
