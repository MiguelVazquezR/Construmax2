<?php

namespace App\Services\Payroll;

use App\Models\AttendanceDevice;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * Short-lived identification issued by the kiosk after a collaborator is
 * recognized. It lets the same person register more than one movement without
 * searching their face again in the biometric provider.
 */
class AttendanceIdentificationTokenService
{
    public const TTL_MINUTES = 10;

    public function issue(
        User $user,
        AttendanceDevice $device,
        string $identifierMethod,
        ?float $similarity = null,
    ): string {
        $payload = [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'identifier_method' => $identifierMethod,
            'similarity' => $similarity,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{user: User, identifier_method: string, similarity: float|null}|null
     */
    public function resolve(string $token, AttendanceDevice $device): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['device_id'] ?? 0) !== $device->id) {
            return null;
        }

        if ((int) ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->whereKey($payload['user_id'] ?? null)->first();

        if (! $user) {
            return null;
        }

        return [
            'user' => $user,
            'identifier_method' => (string) ($payload['identifier_method'] ?? ''),
            'similarity' => isset($payload['similarity']) ? (float) $payload['similarity'] : null,
        ];
    }

    public function expiresInSeconds(): int
    {
        return self::TTL_MINUTES * 60;
    }
}
