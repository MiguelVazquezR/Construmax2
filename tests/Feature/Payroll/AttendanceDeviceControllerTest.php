<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDevice;
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
            );
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

    public function test_destroy_revokes_the_device(): void
    {
        $device = AttendanceDevice::create([
            'name' => 'Tablet obsoleta',
            'token_hash' => hash('sha256', 'token-2'),
            'registered_by' => $this->admin->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('payroll.devices.destroy', $device))
            ->assertOk()
            ->assertJsonPath('device.is_active', false);

        $device->refresh();

        $this->assertFalse($device->is_active);
        $this->assertSame($this->admin->id, $device->revoked_by);
        $this->assertNotNull($device->revoked_at);
    }
}
