<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDevice;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceDeviceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_KEY = 'chrome|Win32|es-MX|800x1280|1|24|America/Mexico_City|8|0|';

    private function fingerprintHash(string $deviceKey = self::DEVICE_KEY): string
    {
        return hash('sha256', 'attendance-device:'.$deviceKey);
    }

    private function configurePassword(string $password = 'Secret123!'): void
    {
        PayrollSetting::current()->update(['device_registration_password' => $password]);
    }

    private function device(array $attributes = []): AttendanceDevice
    {
        return AttendanceDevice::create(array_merge([
            'name' => 'Tablet recepción',
            'token_hash' => hash('sha256', 'token-'.Str::uuid()),
            'fingerprint_hash' => $this->fingerprintHash(),
            'registered_at' => now(),
            'last_seen_at' => now(),
            'last_ip' => '127.0.0.1',
            'is_active' => true,
        ], $attributes));
    }

    public function test_kiosk_exposes_whether_recovery_is_available(): void
    {
        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recoveryPasswordConfigured', false));

        $this->configurePassword();

        $this->get(route('attendance.kiosk.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recoveryPasswordConfigured', true));
    }

    public function test_a_device_is_recognized_again_from_the_same_network_without_a_password(): void
    {
        $device = $this->device();
        $this->configurePassword();

        $response = $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'authorized')
            ->assertJsonPath('device.id', $device->id);

        $token = $response->json('token');

        $this->assertNotEmpty($token);

        // The previous token is replaced, so a lost token can never be replayed.
        $this->assertDatabaseMissing('attendance_devices', ['id' => $device->id, 'token_hash' => hash('sha256', 'old-token')]);

        $this->postJson(route('attendance.kiosk.bootstrap'), [], [
            'X-Attendance-Device' => $token,
        ])->assertOk();
    }

    public function test_recognition_from_another_network_requires_the_registration_password(): void
    {
        $this->configurePassword();
        $this->device(['last_ip' => '10.10.10.10']);

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'password_required')
            ->assertJsonPath('device.name', 'Tablet recepción')
            ->assertJsonMissingPath('token');

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'authorized');

        $this->assertSame(1, AttendanceDevice::count());
    }

    public function test_recognition_rejects_a_wrong_password(): void
    {
        $this->configurePassword();
        $device = $this->device(['last_ip' => '10.10.10.10', 'token_hash' => hash('sha256', 'old-token')]);

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
            'password' => 'WrongPassword',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertSame(hash('sha256', 'old-token'), $device->fresh()->token_hash);
    }

    public function test_recognition_is_unavailable_without_the_shared_password(): void
    {
        $this->device(['last_ip' => '10.10.10.10']);

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'unavailable');
    }

    public function test_an_unknown_fingerprint_can_reactivate_a_chosen_device_with_the_password(): void
    {
        $this->configurePassword();

        $first = $this->device(['name' => 'Tablet entrada']);
        $second = $this->device(['name' => 'Tablet salida']);

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => 'unknown-device-key',
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'choose_device')
            ->assertJsonCount(2, 'devices');

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => 'unknown-device-key',
            'password' => 'Secret123!',
            'device_id' => $second->id,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'authorized')
            ->assertJsonPath('device.id', $second->id);

        // The chosen device keeps its fingerprint so it is recognized next time.
        $this->assertSame(2, AttendanceDevice::count());
        $this->assertSame($this->fingerprintHash(), $first->fresh()->fingerprint_hash);
        $this->assertSame($this->fingerprintHash('unknown-device-key'), $second->fresh()->fingerprint_hash);
    }

    public function test_a_revoked_device_is_never_recognized_again(): void
    {
        $this->configurePassword();

        $this->device([
            'last_ip' => '127.0.0.1',
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'password_required')
            ->assertJsonPath('device', null);

        $this->postJson(route('attendance.kiosk.recover'), [
            'device_key' => self::DEVICE_KEY,
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'choose_device')
            ->assertJsonCount(0, 'devices');
    }

    public function test_recognition_requires_the_device_key(): void
    {
        $this->postJson(route('attendance.kiosk.recover'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_key');
    }

    public function test_the_admin_registration_stores_the_browser_fingerprint(): void
    {
        Permission::create(['name' => 'payroll.devices.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage kiosk devices']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('payroll.devices.manage');

        $this->actingAs($admin)
            ->postJson(route('payroll.devices.store'), [
                'name' => 'Pantalla planta norte',
                'device_key' => self::DEVICE_KEY,
            ])
            ->assertOk();

        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Pantalla planta norte',
            'fingerprint_hash' => $this->fingerprintHash(),
        ]);
    }

    public function test_an_authorized_browser_cannot_be_registered_again(): void
    {
        Permission::create(['name' => 'payroll.devices.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage kiosk devices']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('payroll.devices.manage');

        $this->device(['token_hash' => hash('sha256', 'current-token')]);

        $this->actingAs($admin)
            ->postJson(route('payroll.devices.store'), [
                'name' => 'Duplicado',
                'device_key' => self::DEVICE_KEY,
            ], ['X-Attendance-Device' => 'current-token'])
            ->assertStatus(409)
            ->assertJsonPath('device.name', 'Tablet recepción');

        $this->assertSame(1, AttendanceDevice::count());
        $this->assertDatabaseMissing('attendance_devices', ['name' => 'Duplicado']);
    }

    public function test_the_public_registration_stores_the_browser_fingerprint(): void
    {
        $this->configurePassword();

        $this->postJson(route('attendance.devices.register.store'), [
            'name' => 'Tablet recepción',
            'password' => 'Secret123!',
            'device_key' => self::DEVICE_KEY,
        ])->assertOk();

        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Tablet recepción',
            'fingerprint_hash' => $this->fingerprintHash(),
        ]);
    }

    public function test_the_public_registration_rejects_an_authorized_browser(): void
    {
        $this->configurePassword();

        $this->device(['token_hash' => hash('sha256', 'current-token')]);

        $this->postJson(route('attendance.devices.register.store'), [
            'name' => 'Duplicado',
            'password' => 'Secret123!',
            'device_key' => self::DEVICE_KEY,
        ], ['X-Attendance-Device' => 'current-token'])
            ->assertStatus(409)
            ->assertJsonPath('device.name', 'Tablet recepción');

        $this->assertSame(1, AttendanceDevice::count());
    }

    public function test_bootstrap_keeps_the_fingerprint_up_to_date(): void
    {
        $device = $this->device([
            'fingerprint_hash' => $this->fingerprintHash('old-browser-version'),
            'token_hash' => hash('sha256', 'old-token'),
        ]);

        $this->postJson(route('attendance.kiosk.bootstrap'), [
            'device_key' => self::DEVICE_KEY,
        ], [
            'X-Attendance-Device' => 'old-token',
        ])->assertOk();

        $this->assertSame($this->fingerprintHash(), $device->fresh()->fingerprint_hash);
    }
}
