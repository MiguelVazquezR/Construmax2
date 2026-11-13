<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\IdentifyAttendanceSubjectAction;
use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Services\Payroll\AttendanceDeviceService;
use App\Services\Payroll\AttendanceIdentificationTokenService;
use App\Services\Payroll\AttendancePunchPolicy;
use App\Services\Payroll\AttendanceService;
use App\Services\Payroll\Exceptions\AttendancePunchNotAllowedException;
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
        private readonly IdentifyAttendanceSubjectAction $identifyAction,
        private readonly AttendanceService $attendanceService,
        private readonly AttendancePunchPolicy $punchPolicy,
        private readonly AttendanceIdentificationTokenService $identificationTokens,
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
     * Recognize the collaborator once and return the movements they can
     * register today. The identification is kept in a short-lived token, so
     * the following punches do not search the face again in Amazon.
     */
    public function identify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string', 'in:face,employee_number'],
            'photo' => ['required_if:method,face', 'nullable', 'string', 'max:2000000'],
            'employee_number' => ['required_if:method,employee_number', 'nullable', 'string', 'max:50'],
        ]);

        $device = $request->attributes->get('attendance_device');
        $identity = $this->identifyAction->execute($validated);
        $profile = $identity['profile'];

        $token = $this->identificationTokens->issue(
            $profile->user,
            $device,
            $identity['identifier_method'],
            $identity['similarity'],
        );

        return response()->json($this->identificationPayload(
            $profile,
            $identity['identifier_method'],
            $identity['similarity'],
            $token,
        ));
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
     * Register a punch. The collaborator is identified by the token issued by
     * "identify"; the employee number is still accepted for compatibility.
     */
    public function punch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identification_token' => ['nullable', 'string', 'max:2000'],
            'employee_number' => ['required_without:identification_token', 'nullable', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'string', 'max:2000000'],
        ], self::LOCATION_MESSAGES);

        $device = $request->attributes->get('attendance_device');
        $identification = $this->resolveEmployeeNumberIdentification($validated, $device);

        if ($identification instanceof JsonResponse) {
            return $identification;
        }

        [$profile, $identifierMethod, $similarity] = $identification;

        try {
            $log = $this->registerPunchAction->execute($profile->user, [
                'type' => $validated['type'],
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => $identifierMethod,
                'face_similarity' => $similarity,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'location_accuracy' => $validated['location_accuracy'] ?? null,
                'photo' => $validated['photo'] ?? null,
            ], $device);
        } catch (AttendancePunchNotAllowedException $exception) {
            return $this->punchNotAllowedResponse($exception);
        }

        return response()->json($this->punchPayload($profile, $log, $device, $identifierMethod, $similarity));
    }

    /**
     * Register a punch identified with facial recognition. The photo is only
     * searched in the biometric provider when the request has no token.
     */
    public function facePunch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identification_token' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required_without:identification_token', 'nullable', 'string', 'max:2000000'],
        ], self::LOCATION_MESSAGES);

        $device = $request->attributes->get('attendance_device');
        $identification = $this->resolveFaceIdentification($validated, $device);

        if ($identification instanceof JsonResponse) {
            return $identification;
        }

        [$profile, $identifierMethod, $similarity] = $identification;

        try {
            $log = $this->registerPunchAction->execute($profile->user, [
                'type' => $validated['type'],
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => $identifierMethod,
                'face_similarity' => $similarity,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'location_accuracy' => $validated['location_accuracy'] ?? null,
                'photo' => $validated['photo'] ?? null,
            ], $device);
        } catch (AttendancePunchNotAllowedException $exception) {
            return $this->punchNotAllowedResponse($exception);
        }

        return response()->json($this->punchPayload($profile, $log, $device, $identifierMethod, $similarity));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: PayrollProfile, 1: string, 2: float|null}|JsonResponse
     */
    private function resolveEmployeeNumberIdentification(array $validated, AttendanceDevice $device): array|JsonResponse
    {
        if (filled($validated['identification_token'] ?? null)) {
            return $this->resolveTokenIdentification((string) $validated['identification_token'], $device);
        }

        $identity = $this->identifyAction->execute([
            'method' => 'employee_number',
            'employee_number' => $validated['employee_number'] ?? '',
        ]);

        return [$identity['profile'], $identity['identifier_method'], $identity['similarity']];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: PayrollProfile, 1: string, 2: float|null}|JsonResponse
     */
    private function resolveFaceIdentification(array $validated, AttendanceDevice $device): array|JsonResponse
    {
        if (filled($validated['identification_token'] ?? null)) {
            return $this->resolveTokenIdentification((string) $validated['identification_token'], $device);
        }

        $identity = $this->identifyAction->execute([
            'method' => 'face',
            'photo' => $validated['photo'] ?? '',
        ]);

        return [$identity['profile'], $identity['identifier_method'], $identity['similarity']];
    }

    /**
     * @return array{0: PayrollProfile, 1: string, 2: float|null}|JsonResponse
     */
    private function resolveTokenIdentification(string $token, AttendanceDevice $device): array|JsonResponse
    {
        $identification = $this->identificationTokens->resolve($token, $device);

        if (! $identification) {
            return response()->json([
                'message' => 'Tu identificación expiró. Vuelve a identificarte para continuar.',
                'code' => 'identification_expired',
            ], 422);
        }

        $profile = $identification['user']->payrollProfile;

        if (! $profile || ! $profile->is_attendance_subject) {
            return response()->json([
                'message' => 'El colaborador no tiene la asistencia habilitada.',
                'code' => 'identification_expired',
            ], 422);
        }

        return [$profile, $identification['identifier_method'], $identification['similarity']];
    }

    /**
     * @return array<string, mixed>
     */
    private function identificationPayload(
        PayrollProfile $profile,
        string $identifierMethod,
        ?float $similarity,
        string $token,
    ): array {
        $suggested = $this->attendanceService->suggestedTypeFor($profile->user);

        return [
            'identification_token' => $token,
            'expires_in' => $this->identificationTokens->expiresInSeconds(),
            'user_name' => $profile->user->name,
            'employee_number' => $profile->employee_number,
            'identifier_method' => $identifierMethod,
            'similarity' => $similarity !== null ? round($similarity, 2) : null,
            'options' => $this->punchPolicy->optionsFor($profile->user),
            'suggested_next' => $suggested,
            'suggested_next_label' => AttendanceLog::TYPES[$suggested] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function punchPayload(
        PayrollProfile $profile,
        AttendanceLog $log,
        AttendanceDevice $device,
        string $identifierMethod,
        ?float $similarity,
    ): array {
        $token = $this->identificationTokens->issue(
            $profile->user,
            $device,
            $identifierMethod,
            $similarity,
        );
        $suggested = $this->attendanceService->suggestedTypeFor($profile->user);

        return [
            'identification_token' => $token,
            'expires_in' => $this->identificationTokens->expiresInSeconds(),
            'user_name' => $profile->user->name,
            'employee_number' => $profile->employee_number,
            'type' => $log->type,
            'type_label' => $log->type_label,
            'punched_at' => $log->punched_at->format('H:i'),
            'date_label' => $log->punched_at->format('d/m/Y'),
            'identifier_method' => $identifierMethod,
            'similarity' => $similarity !== null ? round($similarity, 2) : null,
            'options' => $this->punchPolicy->optionsFor($profile->user),
            'suggested_next' => $suggested,
            'suggested_next_label' => AttendanceLog::TYPES[$suggested] ?? null,
        ];
    }

    private function punchNotAllowedResponse(AttendancePunchNotAllowedException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'attendance_punch_not_allowed',
            'type' => $exception->punchType,
            'last_registered_at' => $exception->lastRegisteredAt,
            'options' => $exception->options,
            'errors' => [
                'type' => [$exception->getMessage()],
            ],
        ], 422);
    }
}
