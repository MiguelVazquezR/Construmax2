<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Services\Payroll\AttendanceService;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'pinFallbackEnabled' => $settings->kiosk_pin_fallback_enabled,
            'faceRecognitionEnabled' => $settings->face_recognition_enabled && $this->faceRecognition->isConfigured(),
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
            'pinFallbackEnabled' => PayrollSetting::current()->kiosk_pin_fallback_enabled,
        ]);
    }

    /**
     * Register a punch identified with employee number + kiosk pin.
     */
    public function punch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'string', 'max:12'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'photo' => ['nullable', 'string', 'max:2000000'],
        ]);

        $profile = PayrollProfile::where('employee_number', $validated['employee_number'])
            ->with('user')
            ->first();

        if (
            ! $profile
            || ! $profile->user
            || ! $profile->has_kiosk_pin
            || ! Hash::check($validated['pin'], $profile->kiosk_pin)
        ) {
            throw ValidationException::withMessages([
                'pin' => 'Número de empleado o PIN incorrectos.',
            ]);
        }

        $log = $this->registerPunchAction->execute($profile->user, [
            'type' => $validated['type'],
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
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
     * Register a punch identified with facial recognition (1:N search).
     */
    public function facePunch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'photo' => ['required', 'string', 'max:2000000'],
        ]);

        if (! PayrollSetting::current()->face_recognition_enabled || ! $this->faceRecognition->isConfigured()) {
            throw ValidationException::withMessages([
                'face' => 'El reconocimiento facial no está activo.',
            ]);
        }

        try {
            $match = $this->faceRecognition->search($validated['photo']);
        } catch (\Throwable $exception) {
            Log::warning('Facial kiosk search failed.', ['error' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'face' => 'No se pudo procesar el rostro. Intenta de nuevo o usa tu PIN.',
            ]);
        }

        if (! $match || $match['external_image_id'] === '') {
            throw ValidationException::withMessages([
                'face' => 'No se pudo reconocer el rostro. Intenta de nuevo o usa tu PIN.',
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
