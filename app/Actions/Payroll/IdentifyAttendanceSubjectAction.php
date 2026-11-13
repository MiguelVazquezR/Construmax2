<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Services\Payroll\FaceRecognition\Exceptions\FaceNotDetectedException;
use App\Services\Payroll\FaceRecognition\Exceptions\MultipleFacesDetectedException;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Identify the collaborator standing in front of the kiosk, either with the
 * face (1:N search) or with the employee number. The search is done once per
 * identification session; the caller stores it in a short-lived token.
 */
class IdentifyAttendanceSubjectAction
{
    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * @param  array{method: string, photo?: string|null, employee_number?: string|null}  $data
     * @return array{profile: PayrollProfile, identifier_method: string, similarity: float|null}
     */
    public function execute(array $data): array
    {
        return match ($data['method'] ?? '') {
            'face' => $this->identifyByFace((string) ($data['photo'] ?? '')),
            'employee_number' => $this->identifyByEmployeeNumber((string) ($data['employee_number'] ?? '')),
            default => throw ValidationException::withMessages([
                'method' => 'El método de identificación no es válido.',
            ]),
        };
    }

    /**
     * @return array{profile: PayrollProfile, identifier_method: string, similarity: float|null}
     */
    private function identifyByFace(string $photo): array
    {
        if (! $this->faceRecognition->isConfigured()) {
            throw ValidationException::withMessages([
                'face' => 'El reconocimiento facial no está configurado. Pide ayuda al administrador.',
            ]);
        }

        try {
            $match = $this->faceRecognition->search($photo);
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

        return [
            'profile' => $profile,
            'identifier_method' => AttendanceLog::IDENTIFIER_FACE,
            'similarity' => (float) $match['similarity'],
        ];
    }

    /**
     * @return array{profile: PayrollProfile, identifier_method: string, similarity: float|null}
     */
    private function identifyByEmployeeNumber(string $employeeNumber): array
    {
        if (! PayrollSetting::current()->kiosk_employee_number_enabled) {
            throw ValidationException::withMessages([
                'employee_number' => 'El registro con número de empleado no está activo. Usa tu rostro o pide ayuda al administrador.',
            ]);
        }

        $profile = PayrollProfile::query()
            ->whereRaw('LOWER(employee_number) = ?', [mb_strtolower(trim($employeeNumber))])
            ->with('user')
            ->get()
            ->first(fn (PayrollProfile $profile) => $profile->user !== null);

        if (! $profile) {
            throw ValidationException::withMessages([
                'employee_number' => 'Número de empleado no encontrado. Verifícalo o pide ayuda al administrador.',
            ]);
        }

        if (! $profile->is_attendance_subject) {
            throw ValidationException::withMessages([
                'employee_number' => 'El colaborador no tiene la asistencia habilitada.',
            ]);
        }

        return [
            'profile' => $profile,
            'identifier_method' => AttendanceLog::IDENTIFIER_EMPLOYEE_NUMBER,
            'similarity' => null,
        ];
    }
}
