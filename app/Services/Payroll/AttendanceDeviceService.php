<?php

namespace App\Services\Payroll;

use App\Models\AttendanceDevice;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceDeviceService
{
    /**
     * Register a new authorized kiosk device.
     *
     * The plain token is returned only once: it must be stored in the device
     * browser (localStorage) and is kept hashed in the database. The browser
     * fingerprint is kept hashed too, so the device can be recognized again
     * when its local storage is cleared.
     *
     * @return array{device: AttendanceDevice, token: string}
     */
    public function register(
        string $name,
        ?string $location,
        ?string $notes,
        ?User $actor = null,
        ?string $fingerprint = null,
    ): array {
        $token = Str::random(64);

        $device = AttendanceDevice::create([
            'name' => $name,
            'token_hash' => $this->hashToken($token),
            'fingerprint_hash' => $this->hashFingerprint($fingerprint),
            'location' => $location,
            'notes' => $notes,
            'registered_by' => $actor?->id,
            'registered_at' => now(),
            'is_active' => true,
        ]);

        return ['device' => $device, 'token' => $token];
    }

    /**
     * Resolve the device row of a tablet running the Android kiosk app,
     * creating it on first contact.
     *
     * The app needs no authorization at all: it is installed manually from a
     * controlled APK, so the tablet is recognized by its Android ID and the row
     * exists only to keep every punch traceable. The random token of that row
     * is never handed out, so it cannot be used to reach the web kiosk.
     */
    public function findOrCreateForApp(?string $deviceKey, ?string $deviceName = null): ?AttendanceDevice
    {
        $hash = $this->hashFingerprint($deviceKey);

        if ($hash === null) {
            return null;
        }

        $device = AttendanceDevice::query()
            ->where('fingerprint_hash', $hash)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->first();

        if ($device) {
            return $device;
        }

        return AttendanceDevice::create([
            'name' => $this->appDeviceName($deviceName),
            'token_hash' => $this->hashToken(Str::random(64)),
            'fingerprint_hash' => $hash,
            'notes' => 'Registrado automáticamente por la app de kiosco.',
            'registered_at' => now(),
            'is_active' => true,
        ]);
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
     * Active devices registered from the same browser fingerprint, the most
     * recently used first.
     *
     * @return \Illuminate\Support\Collection<int, AttendanceDevice>
     */
    public function findByFingerprint(?string $fingerprint): Collection
    {
        $hash = $this->hashFingerprint($fingerprint);

        if ($hash === null) {
            return collect();
        }

        return AttendanceDevice::active()
            ->where('fingerprint_hash', $hash)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The device that most likely belongs to the browser asking for a token:
     * the same network is preferred (removes most collisions between identical
     * tablets), otherwise the most recently used one.
     */
    public function bestMatchForFingerprint(?string $fingerprint, ?string $ip): ?AttendanceDevice
    {
        $matches = $this->findByFingerprint($fingerprint);

        if ($ip !== null && $ip !== '') {
            $sameNetwork = $matches->first(
                fn (AttendanceDevice $device) => $device->last_ip === $ip
            );

            if ($sameNetwork) {
                return $sameNetwork;
            }
        }

        return $matches->first();
    }

    /**
     * Active devices an operator can pick from when the fingerprint does not
     * identify a single device (for example after a browser update).
     *
     * @return array<int, array{id: int, name: string, location: string|null}>
     */
    public function authorizableDevices(): array
    {
        return AttendanceDevice::active()
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'location'])
            ->map(fn (AttendanceDevice $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ])
            ->all();
    }

    /**
     * Replace the device token and return the new plain value. The previous
     * token stops working, so a lost token can never be replayed.
     */
    public function issueToken(AttendanceDevice $device): string
    {
        $token = Str::random(64);

        $device->forceFill(['token_hash' => $this->hashToken($token)])->save();

        return $token;
    }

    /**
     * Keep the fingerprint of an authorized device up to date: browser updates
     * change it slightly, and an outdated fingerprint would stop recognizing
     * the device.
     */
    public function syncFingerprint(AttendanceDevice $device, ?string $fingerprint): void
    {
        $hash = $this->hashFingerprint($fingerprint);

        if ($hash === null || $device->fingerprint_hash === $hash) {
            return;
        }

        $device->forceFill(['fingerprint_hash' => $hash])->save();
    }

    /**
     * The device is on the same network it used before, so it is safe to
     * recognize it again without asking for the shared password.
     */
    public function isOnKnownNetwork(AttendanceDevice $device, ?string $ip): bool
    {
        return filled($device->last_ip)
            && filled($ip)
            && $device->last_ip === $ip;
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

    /**
     * Name for a tablet that registered itself: the model reported by the app,
     * prefixed so the administrator recognizes it in the devices module.
     */
    private function appDeviceName(?string $deviceName): string
    {
        $deviceName = trim((string) $deviceName);

        return $deviceName === '' ? 'Tablet Android' : Str::limit('Tablet '.$deviceName, 100, '');
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The fingerprint never reaches the database in plain text.
     */
    private function hashFingerprint(?string $fingerprint): ?string
    {
        $fingerprint = trim((string) $fingerprint);

        if ($fingerprint === '') {
            return null;
        }

        return hash('sha256', 'attendance-device:'.$fingerprint);
    }
}
