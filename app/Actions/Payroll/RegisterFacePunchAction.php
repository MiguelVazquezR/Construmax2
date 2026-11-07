<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Services\Payroll\FaceRecognition\Exceptions\FaceNotDetectedException;
use App\Services\Payroll\FaceRecognition\Exceptions\MultipleFacesDetectedException;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Register a kiosk punch identified with facial recognition (1:N search).
 *
 * The provider is called with the captured frame; a match resolves to the
 * collaborator's payroll profile, so no employee number is needed.
 */
class RegisterFacePunchAction
{
    public function __construct(
        private readonly RegisterAttendancePunchAction $registerPunchAction,
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * @param  array{type: string, photo: string, latitude: float|string|null, longitude: float|string|null, location_accuracy?: float|string|null}  $data
     * @return array{log: AttendanceLog, similarity: float}
     */
    public function execute(array $data, ?AttendanceDevice $device = null): array
    {
        if (! $this->faceRecognition->isConfigured()) {
            throw ValidationException::withMessages([
                'face' => 'El reconocimiento facial no está configurado. Pide ayuda al administrador.',
            ]);
        }

        try {
            $match = $this->faceRecognition->search($data['photo']);
        } catch (FaceNotDetectedException) {
            throw ValidationException::withMessages([
                'face' => 'No se detectó un rostro en la foto. Colócate de frente a la cámara, con buena iluminación, e intenta de nuevo.',
            ]);
        } catch (MultipleFacesDetectedException) {
            throw ValidationException::withMessages([
                'face' => 'La foto muestra más de un rostro. Asegúrate de estar solo frente a la cámara e intenta de nuevo.',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Facial kiosk search failed.', [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'face' => 'No se pudo procesar el rostro. Intenta de nuevo.',
            ]);
        }

        if (! $match || $match['external_image_id'] === '') {
            throw ValidationException::withMessages([
                'face' => 'Tu rostro no coincide con ningún rostro registrado. Mira de frente a la cámara e intenta de nuevo; si el problema continúa, pide al administrador que actualice tu registro facial.',
            ]);
        }

        $profile = PayrollProfile::where('user_id', (int) $match['external_image_id'])
            ->with('user')
            ->first();

        if (! $profile?->user || ! $profile->is_attendance_subject) {
            throw ValidationException::withMessages([
                'face' => 'El rostro no corresponde a un colaborador con asistencia activa.',
            ]);
        }

        $log = $this->registerPunchAction->execute($profile->user, [
            'type' => $data['type'],
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_FACE,
            'face_similarity' => $match['similarity'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_accuracy' => $data['location_accuracy'] ?? null,
            'photo' => $data['photo'],
        ], $device);

        return ['log' => $log, 'similarity' => (float) $match['similarity']];
    }
}
