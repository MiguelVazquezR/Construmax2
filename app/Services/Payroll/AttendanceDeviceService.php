<?php

namespace App\Services\Payroll;

use App\Models\AttendanceDevice;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceDeviceService
{
    /**
     * Register a new authorized kiosk device.
     *
     * The plain token is returned only once: it must be stored in the device
     * browser (localStorage) and is kept hashed in the database.
     *
     * @return array{device: AttendanceDevice, token: string}
     */
    public function register(string $name, ?string $location, ?string $notes, ?User $actor = null): array
    {
        $token = Str::random(64);

        $device = AttendanceDevice::create([
            'name' => $name,
            'token_hash' => $this->hashToken($token),
            'location' => $location,
            'notes' => $notes,
            'registered_by' => $actor?->id,
            'registered_at' => now(),
            'is_active' => true,
        ]);

        return ['device' => $device, 'token' => $token];
    }

    /**
     * Public registration is only possible while the shared password is set.
     */
    public function registrationPasswordIsConfigured(): bool
    {
        return filled($this->registrationPassword());
    }

    /**
     * The shared password is shown as plain text in the devices module.
     */
    public function registrationPassword(): ?string
    {
        return PayrollSetting::current()->device_registration_password;
    }

    public function verifyRegistrationPassword(?string $password): bool
    {
        $stored = $this->registrationPassword();

        return filled($stored)
            && filled($password)
            && hash_equals((string) $stored, trim((string) $password));
    }

    /**
     * Replace the shared password used by the public registration link.
     */
    public function setRegistrationPassword(string $password): void
    {
        $settings = PayrollSetting::current();
        $settings->device_registration_password = trim($password);
        $settings->save();
    }

    /**
     * Find an active device by its plain token.
     */
    public function findByToken(?string $token): ?AttendanceDevice
    {
        if (empty($token)) {
            return null;
        }

        return AttendanceDevice::active()
            ->where('token_hash', $this->hashToken($token))
            ->first();
    }

    /**
     * Keep track of every device that talks to the kiosk API.
     */
    public function touch(AttendanceDevice $device, Request $request): void
    {
        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();
    }

    public function revoke(AttendanceDevice $device, User $actor): void
    {
        $device->forceFill([
            'is_active' => false,
            'revoked_by' => $actor->id,
            'revoked_at' => now(),
        ])->save();
    }

    /**
     * Update the descriptive data of a device.
     */
    public function update(AttendanceDevice $device, array $data): AttendanceDevice
    {
        $device->update([
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $device;
    }

    public function restore(AttendanceDevice $device): void
    {
        $device->forceFill([
            'is_active' => true,
            'revoked_by' => null,
            'revoked_at' => null,
        ])->save();
    }

    public function delete(AttendanceDevice $device): void
    {
        $device->delete();
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
