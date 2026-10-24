<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Services\Payroll\AttendanceService;
use App\Services\Payroll\FaceRecognition\Exceptions\FaceNotDetectedException;
use App\Services\Payroll\FaceRecognition\Exceptions\MultipleFacesDetectedException;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public attendance kiosk. Every API call is restricted by the
 * "attendance.device" middleware to registered devices.
 */
class KioskController extends Controller
{
    public function __construct(
        private readonly RegisterAttendancePunchAction $registerPunchAction,
        private readonly AttendanceService $attendanceService,
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    public function show(): Response
    {
        $settings = PayrollSetting::current();

        return Inertia::render('Payroll/Kiosk/Index', [
            'punchTypes' => AttendanceLog::TYPES,
            // The kiosk always offers the face option; this flag only tells the
            // page whether the biometric provider is ready to identify faces.
            'faceRecognitionEnabled' => $this->faceRecognition->isConfigured(),
            'employeeNumberEnabled' => (bool) $settings->kiosk_employee_number_enabled,
            'appName' => config('app.name'),
        ]);
    }

    /**
     * Validate the stored device token and return the kiosk context.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $device = $request->attributes->get('attendance_device');

        return response()->json([
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ],
            'punchTypes' => AttendanceLog::TYPES,
        ]);
    }

    /**
     * Register a punch identified with the collaborator's employee number.
     */
    public function punch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'string', 'max:2000000'],
        ]);

        $profile = $this->findProfileByEmployeeNumber($validated['employee_number']);

        if (! $profile) {
            throw ValidationException::withMessages([
                'employee_number' => 'Número de empleado no encontrado. Verifícalo o pide ayuda al administrador.',
            ]);
        }

        $log = $this->registerPunchAction->execute($profile->user, [
            'type' => $validated['type'],
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_EMPLOYEE_NUMBER,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'photo' => $validated['photo'] ?? null,
        ], $request->attributes->get('attendance_device'));

        return response()->json([
            'user_name' => $profile->user->name,
            'employee_number' => $profile->employee_number,
            'type' => $log->type,
            'type_label' => $log->type_label,
            'punched_at' => $log->punched_at->format('H:i'),
            'date_label' => $log->punched_at->format('d/m/Y'),
            'suggested_next' => $this->attendanceService->suggestedTypeFor($profile->user),
            'suggested_next_label' => AttendanceLog::TYPES[$this->attendanceService->suggestedTypeFor($profile->user)] ?? null,
        ]);
    }

    /**
     * Find the collaborator whose employee number matches (case-insensitive).
     */
    private function findProfileByEmployeeNumber(string $employeeNumber): ?PayrollProfile
    {
        return PayrollProfile::query()
            ->whereRaw('LOWER(employee_number) = ?', [mb_strtolower(trim($employeeNumber))])
            ->with('user')
            ->get()
            ->first(fn (PayrollProfile $profile) => $profile->user !== null);
    }

    /**
     * Register a punch identified with facial recognition (1:N search).
     */
    public function facePunch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'string', 'max:2000000'],
        ]);

        if (! $this->faceRecognition->isConfigured()) {
            throw ValidationException::withMessages([
                'face' => 'El reconocimiento facial no está configurado. Pide ayuda al administrador.',
            ]);
        }

        try {
            $match = $this->faceRecognition->search($validated['photo']);
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
            'type' => $validated['type'],
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_FACE,
            'face_similarity' => $match['similarity'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'photo' => $validated['photo'],
        ], $request->attributes->get('attendance_device'));

        return response()->json([
            'user_name' => $profile->user->name,
            'employee_number' => $profile->employee_number,
            'type' => $log->type,
            'type_label' => $log->type_label,
            'punched_at' => $log->punched_at->format('H:i'),
            'date_label' => $log->punched_at->format('d/m/Y'),
            'similarity' => round($match['similarity'], 2),
            'suggested_next' => $this->attendanceService->suggestedTypeFor($profile->user),
            'suggested_next_label' => AttendanceLog::TYPES[$this->attendanceService->suggestedTypeFor($profile->user)] ?? null,
        ]);
    }
}
