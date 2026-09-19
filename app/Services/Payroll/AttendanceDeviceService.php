<?php

namespace App\Services\Payroll;

use App\Models\AttendanceDevice;
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
    public function register(string $name, ?string $location, ?string $notes, User $actor): array
    {
        $token = Str::random(64);

        $device = AttendanceDevice::create([
            'name' => $name,
            'token_hash' => $this->hashToken($token),
            'location' => $location,
            'notes' => $notes,
            'registered_by' => $actor->id,
            'registered_at' => now(),
            'is_active' => true,
        ]);

        return ['device' => $device, 'token' => $token];
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

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
