<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RegisterAttendancePunchAction
{
    /**
     * Punches of the same type inside this window are considered duplicates.
     */
    public const DUPLICATE_WINDOW_MINUTES = 2;

    /**
     * Register an attendance punch for a collaborator.
     *
     * @param  array{
     *     type: string,
     *     punched_at?: mixed,
     *     source?: string,
     *     identifier_method?: string,
     *     face_similarity?: float|null,
     *     latitude?: float|null,
     *     longitude?: float|null,
     *     location_accuracy?: float|null,
     *     photo?: string|null,
     *     edit_reason?: string|null,
     * }  $data
     */
    public function execute(User $user, array $data, ?AttendanceDevice $device = null): AttendanceLog
    {
        $profile = $user->payrollProfile;

        if (! $profile || ! $profile->is_attendance_subject) {
            throw ValidationException::withMessages([
                'user' => 'El colaborador no tiene la asistencia habilitada.',
            ]);
        }

        $type = $data['type'];

        if (! array_key_exists($type, AttendanceLog::TYPES)) {
            throw ValidationException::withMessages([
                'type' => 'El tipo de marcaje no es válido.',
            ]);
        }

        $punchedAt = isset($data['punched_at']) ? Carbon::parse($data['punched_at']) : now();
        $source = $data['source'] ?? AttendanceLog::SOURCE_KIOSK;

        if ($this->alreadyRegistered($user->id, $type, $punchedAt)) {
            throw ValidationException::withMessages([
                'type' => 'Este marcaje ya se registró hace unos momentos.',
            ]);
        }

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if ($source === AttendanceLog::SOURCE_REMOTE) {
            $settings = PayrollSetting::current();

            if ($settings->remote_geolocation_required && ($latitude === null || $longitude === null)) {
                throw ValidationException::withMessages([
                    'location' => 'No se pudo obtener tu ubicación. Actívala e intenta de nuevo.',
                ]);
            }
        }

        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_device_id' => $device?->id,
            'type' => $type,
            'punched_at' => $punchedAt,
            'source' => $source,
            'identifier_method' => $data['identifier_method'] ?? AttendanceLog::IDENTIFIER_PIN,
            'face_similarity' => $data['face_similarity'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_accuracy' => $data['location_accuracy'] ?? null,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'edit_reason' => $data['edit_reason'] ?? null,
        ]);

        if (! empty($data['photo'])) {
            $this->storeCapture($log, $data['photo']);
        }

        return $log;
    }

    private function alreadyRegistered(int $userId, string $type, Carbon $punchedAt): bool
    {
        return AttendanceLog::forUser($userId)
            ->where('type', $type)
            ->whereBetween('punched_at', [
                $punchedAt->copy()->subMinutes(self::DUPLICATE_WINDOW_MINUTES),
                $punchedAt->copy()->addMinutes(self::DUPLICATE_WINDOW_MINUTES),
            ])
            ->exists();
    }

    /**
     * Store the captured frame as evidence. The image may arrive as a data URL
     * (data:image/jpeg;base64,...) sent by the kiosk camera.
     */
    private function storeCapture(AttendanceLog $log, string $photo): void
    {
        try {
            $log->addMediaFromBase64($photo)
                ->usingFileName('attendance-'.$log->id.'.jpg')
                ->toMediaCollection('capture');
        } catch (\Throwable $exception) {
            // The punch is valid even if the evidence cannot be stored.
            Log::warning('No se pudo guardar la captura del marcaje.', [
                'attendance_log_id' => $log->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
