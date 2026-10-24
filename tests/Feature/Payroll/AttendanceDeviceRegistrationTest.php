<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function configurePassword(string $password = 'Secret123!'): void
    {
        $settings = PayrollSetting::current();
        $settings->device_registration_password = $password;
        $settings->save();
    }

    public function test_registration_page_renders_publicly(): void
    {
        $this->get(route('attendance.devices.register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Devices/Register')
                ->has('kioskUrl')
                ->where('passwordConfigured', false)
            );
    }

    public function test_device_can_be_registered_with_the_shared_password(): void
    {
        $this->configurePassword();

        $response = $this->postJson(route('attendance.devices.register.store'), [
            'name' => 'Tablet recepción',
            'location' => 'Oficina',
            'notes' => 'Registro público',
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonPath('device.name', 'Tablet recepción');

        $token = $response->json('token');

        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Tablet recepción',
            'token_hash' => hash('sha256', $token),
            'registered_by' => null,
            'is_active' => true,
        ]);

        $this->postJson(route('attendance.kiosk.bootstrap'), [], [
            'X-Attendance-Device' => $token,
        ])->assertOk();
    }

    public function test_registration_rejects_a_wrong_password(): void
    {
        $this->configurePassword();

        $this->postJson(route('attendance.devices.register.store'), [
            'name' => 'Tablet recepción',
            'password' => 'WrongPassword',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('attendance_devices', 0);
    }

    public function test_registration_is_rejected_without_a_configured_password(): void
    {
        $this->postJson(route('attendance.devices.register.store'), [
            'name' => 'Tablet recepción',
            'password' => 'Secret123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('attendance_devices', 0);
    }
}
