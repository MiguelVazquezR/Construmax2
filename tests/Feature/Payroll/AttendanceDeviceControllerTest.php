<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDevice;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceDeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.devices.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage kiosk devices']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.devices.manage');
    }

    public function test_index_renders_the_devices_page(): void
    {
        AttendanceDevice::create([
            'name' => 'Tablet recepción',
            'token_hash' => hash('sha256', 'token-1'),
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.devices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Devices/Index')
                ->has('devices', 1)
                ->has('kioskUrl')
                ->has('registrationUrl')
                ->where('registrationPasswordConfigured', false)
            );
    }

    public function test_update_modifies_the_device(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Tablet recepción',
            'token_hash' => hash('sha256', 'token-update'),
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('payroll.devices.update', $device), [
                'name' => 'Tablet entrada',
                'location' => 'Recepción',
                'notes' => 'Actualizada',
            ])
            ->assertOk()
            ->assertJsonPath('device.name', 'Tablet entrada');

        $this->assertDatabaseHas('attendance_devices', [
            'id' => $device->id,
            'name' => 'Tablet entrada',
            'location' => 'Recepción',
            'notes' => 'Actualizada',
        ]);
    }

    public function test_update_registration_password_stores_plain_text_with_three_characters(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('payroll.devices.registration-password'), [
                'password' => 'abc',
            ])
            ->assertOk()
            ->assertJsonPath('configured', true);

        $this->assertSame('abc', PayrollSetting::current()->device_registration_password);
    }

    public function test_update_registration_password_rejects_less_than_three_characters(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('payroll.devices.registration-password'), [
                'password' => 'ab',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_update_registration_password_requires_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->putJson(route('payroll.devices.registration-password'), [
                'password' => 'Secret123!',
            ])
            ->assertForbidden();
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('payroll.devices.index'))
            ->assertForbidden();
    }

    public function test_store_registers_a_device_and_returns_the_plain_token_once(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('payroll.devices.store'), [
                'name' => 'Pantalla planta norte',
                'location' => 'Planta',
                'notes' => 'Tablet montada en pared',
            ])
            ->assertOk()
            ->assertJsonPath('device.name', 'Pantalla planta norte');

        $token = $response->json('token');

        $this->assertNotEmpty($token);

        // The token is stored hashed, never in plain text.
        $this->assertDatabaseMissing('attendance_devices', ['token_hash' => $token]);
        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Pantalla planta norte',
            'token_hash' => hash('sha256', $token),
            'registered_by' => $this->admin->id,
            'is_active' => true,
        ]);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('payroll.devices.store'), ['name' => 'Tablet'])
            ->assertForbidden();
    }

    public function test_revoke_deactivates_the_device(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Tablet obsoleta',
            'token_hash' => hash('sha256', 'token-2'),
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('payroll.devices.revoke', $device))
            ->assertOk()
            ->assertJsonPath('device.is_active', false);

        $device->refresh();

        $this->assertFalse($device->is_active);
        $this->assertSame($this->admin->id, $device->revoked_by);
        $this->assertNotNull($device->revoked_at);
    }

    public function test_restore_reactivates_a_revoked_device(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Tablet recepción',
            'token_hash' => hash('sha256', 'token-3'),
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
            'is_active' => false,
            'revoked_by' => $this->admin->id,
            'revoked_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('payroll.devices.restore', $device))
            ->assertOk()
            ->assertJsonPath('device.is_active', true);

        $device->refresh();

        $this->assertTrue($device->is_active);
        $this->assertNull($device->revoked_by);
        $this->assertNull($device->revoked_at);
    }

    public function test_destroy_deletes_the_device_permanently(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Tablet obsoleta',
            'token_hash' => hash('sha256', 'token-4'),
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('payroll.devices.destroy', $device))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('attendance_devices', ['id' => $device->id]);
    }
}
