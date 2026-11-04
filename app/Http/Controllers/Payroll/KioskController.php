<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Services\Payroll\AttendanceDeviceService;
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
    /**
     * Every kiosk punch stores the device location, so the coordinates are
     * required: a punch without them would be unusable evidence.
     */
    private const LOCATION_MESSAGES = [
        'latitude.required' => 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.',
        'longitude.required' => 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.',
    ];

    public function __construct(
        private readonly RegisterAttendancePunchAction $registerPunchAction,
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceDeviceService $deviceService,
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
            'registrationUrl' => route('attendance.devices.register'),
            'recoveryPasswordConfigured' => $this->deviceService->registrationPasswordIsConfigured(),
            'appName' => config('app.name'),
        ]);
    }

    /**
     * Validate the stored device token and return the kiosk context.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $device = $request->attributes->get('attendance_device');

        $request->validate([
            'device_key' => ['nullable', 'string', 'max:255'],
        ]);

        // The browser reports its fingerprint on every load: browser updates
        // shift it slightly and the device must stay recognizable.
        $this->deviceService->syncFingerprint($device, $request->input('device_key'));

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
     * Recognize a device that lost its token (cleared browsing data, browser
     * storage eviction, iOS storage cleanup) without registering it again.
     *
     * The device is recognized automatically when its fingerprint matches a
     * registered device that still uses the same network it used before. Any
     * other case requires the shared registration password, the same one used
     * by the public registration link, because a fingerprint is not a secret.
     */
    public function recover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_key' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'integer'],
        ], [
            'device_key.required' => 'No se pudo identificar este dispositivo. Recarga la página e intenta de nuevo.',
        ]);

        $ip = $request->ip();
        $device = $this->deviceService->bestMatchForFingerprint($validated['device_key'], $ip);

        if (filled($validated['password'] ?? null)) {
            return $this->recoverWithPassword($request, $validated, $device);
        }

        if ($device && $this->deviceService->isOnKnownNetwork($device, $ip)) {
            return $this->authorizeDevice($request, $device);
        }

        if (! $this->deviceService->registrationPasswordIsConfigured()) {
            return response()->json([
                'status' => 'unavailable',
                'device' => null,
                'message' => 'La reactivación de dispositivos está desactivada. Pide al administrador que configure la contraseña de registro en Dispositivos de asistencia.',
            ]);
        }

        return response()->json([
            'status' => 'password_required',
            'device' => $this->devicePayload($device),
            'message' => $device
                ? 'Este dispositivo ya estaba registrado. Escribe la contraseña de registro para reactivarlo.'
                : 'Escribe la contraseña de registro para reactivar un dispositivo ya registrado.',
        ]);
    }

    /**
     * @param  array{device_key: string, password?: string|null, device_id?: int|null}  $validated
     */
    private function recoverWithPassword(Request $request, array $validated, ?AttendanceDevice $device): JsonResponse
    {
        if (! $this->deviceService->registrationPasswordIsConfigured()) {
            throw ValidationException::withMessages([
                'password' => 'La reactivación de dispositivos está desactivada. Pide al administrador que configure la contraseña de registro.',
            ]);
        }

        if (! $this->deviceService->verifyRegistrationPassword($validated['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Contraseña de registro incorrecta. Verifícala e intenta de nuevo.',
            ]);
        }

        if (filled($validated['device_id'] ?? null)) {
            $device = AttendanceDevice::active()->find($validated['device_id']);

            if (! $device) {
                throw ValidationException::withMessages([
                    'device_id' => 'El dispositivo seleccionado ya no está autorizado. Pide al administrador que lo revise.',
                ]);
            }
        }

        if (! $device) {
            // The fingerprint does not point to a single device (a browser
            // update changed it, for example): the operator picks the right one.
            return response()->json([
                'status' => 'choose_device',
                'device' => null,
                'devices' => $this->deviceService->authorizableDevices(),
                'message' => 'Elige el dispositivo que estás usando para reactivarlo.',
            ]);
        }

        return $this->authorizeDevice($request, $device);
    }

    private function authorizeDevice(Request $request, AttendanceDevice $device): JsonResponse
    {
        $token = $this->deviceService->issueToken($device);

        $this->deviceService->syncFingerprint($device, $request->input('device_key'));
        $this->deviceService->touch($device, $request);

        Log::info('Attendance kiosk device recognized again.', [
            'attendance_device_id' => $device->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'authorized',
            'device' => $this->devicePayload($device),
            'token' => $token,
        ]);
    }

    /**
     * @return array{id: int, name: string, location: string|null}|null
     */
    private function devicePayload(?AttendanceDevice $device): ?array
    {
        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->location,
        ];
    }

    /**
     * Register a punch identified with the collaborator's employee number.
     */
    public function punch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'string', 'max:2000000'],
        ], self::LOCATION_MESSAGES);

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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'string', 'max:2000000'],
        ], self::LOCATION_MESSAGES);

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
