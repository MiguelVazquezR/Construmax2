<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\FaceEnrollment;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Payroll\AttendanceDayService;
use App\Services\Payroll\AttendanceDaySummary;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use App\Services\Payroll\VacationPeriodService;
use App\Services\Payroll\VacationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service attendance portal of a collaborator: remote punches with
 * geolocation, own history, vacations, payslips and face enrollment.
 */
class MyAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceDayService $attendanceDayService,
        private readonly VacationService $vacationService,
        private readonly VacationPeriodService $vacationPeriodService,
        private readonly RegisterAttendancePunchAction $registerPunchAction,
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->payrollProfile;

        if (! $profile || ! $profile->is_attendance_subject) {
            abort(403);
        }

        $settings = PayrollSetting::current();
        $today = $this->attendanceDayService->summaryFor($user, CarbonImmutable::today());

        // History: day by day detail of the payroll period in progress, so the
        // collaborator reviews exactly the days the payroll team is working
        // on. The range only moves when the weekly period rolls over (every
        // Monday) or when the payroll team opens a new period.
        [$periodFrom, $periodTo] = $this->currentPeriodRange();

        $periodDays = collect();
        $cursor = $periodFrom;

        while ($cursor->lessThanOrEqualTo($periodTo)) {
            $periodDays->push($this->mapDay($this->attendanceDayService->summaryFor($user, $cursor)));
            $cursor = $cursor->addDay();
        }

        $vacationRequests = VacationRequest::query()
            ->forUser($user->id)
            ->with(['reviewer:id,name', 'requestedBy:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (VacationRequest $vacationRequest) => [
                'id' => $vacationRequest->id,
                'start_date' => $vacationRequest->start_date->toDateString(),
                'end_date' => $vacationRequest->end_date->toDateString(),
                'days' => (float) $vacationRequest->days,
                'status' => $vacationRequest->status,
                'status_label' => $vacationRequest->statusLabel(),
                'reason' => $vacationRequest->reason,
                'review_notes' => $vacationRequest->review_notes,
                'reviewer_name' => $vacationRequest->reviewer?->name,
                'requested_by_name' => $vacationRequest->requestedBy?->name,
                'requested_at' => $vacationRequest->created_at?->toDateString(),
                'is_pending' => $vacationRequest->isPending(),
            ])
            ->values()
            ->all();

        $payslips = Payslip::query()
            ->where('user_id', $user->id)
            ->with('period:id,type,start_date,end_date,status')
            ->orderByDesc('id')
            ->limit(24)
            ->get()
            ->map(fn (Payslip $payslip) => [
                'id' => $payslip->id,
                'period_id' => $payslip->payroll_period_id,
                'period_label' => $payslip->period?->label(),
                'total_gross' => (float) $payslip->total_gross,
                'total_deductions' => (float) $payslip->total_deductions,
                'total_net' => (float) $payslip->total_net,
                'days_paid' => (float) $payslip->days_paid,
                'generated_at' => $payslip->generated_at?->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        $vacationBalance = $this->vacationService->balanceFor($user);

        // The collaborator reviews the same stored periods (and premium
        // status) managed by the payroll team.
        $this->vacationPeriodService->syncFor($user, $vacationBalance['seasons']);

        return Inertia::render('Payroll/MyAttendance/Index', [
            'profile' => [
                'employee_number' => $profile->employee_number,
                'can_remote_attendance' => (bool) $profile->can_remote_attendance,
                // The pin itself is never exposed; the portal only needs to
                // know whether the collaborator can identify with one.
                'has_kiosk_pin' => $profile->kiosk_pin !== null,
                'hire_date' => $profile->hire_date?->toDateString(),
                'termination_date' => $profile->termination_date?->toDateString(),
                // Dismissed collaborators keep their history and receipts but cannot punch any more.
                'can_punch' => ! ($profile->termination_date && $profile->termination_date->toDateString() < CarbonImmutable::today()->toDateString()),
            ],
            'today' => $this->mapDay($today),
            'suggestedNextType' => $this->suggestedNextType($user),
            'periodDays' => $periodDays->values()->all(),
            'historyRange' => [
                'start_date' => $periodFrom->toDateString(),
                'end_date' => $periodTo->toDateString(),
            ],
            'vacationBalance' => $vacationBalance,
            'vacationMovements' => $this->vacationService->movementsFor($user),
            'vacationPeriods' => $this->vacationPeriodService->payloadFor($user),
            'vacationRequests' => $vacationRequests,
            'payslips' => $payslips,
            'punchTypes' => AttendanceLog::TYPES,
            'vacationMinDays' => (float) $settings->vacation_min_days_to_request,
            'remoteGeolocationRequired' => (bool) $settings->remote_geolocation_required,
            'remotePinEnabled' => (bool) $settings->kiosk_pin_fallback_enabled,
            'faceRecognition' => [
                'enabled' => (bool) $settings->face_recognition_enabled,
                'configured' => $this->faceRecognition->isConfigured(),
                'activeCount' => FaceEnrollment::query()
                    ->forUser($user->id)
                    ->active()
                    ->count(),
            ],
        ]);
    }

    /**
     * Register a remote punch with geolocation. The collaborator identifies
     * with their face or with their kiosk pin; when no method is active the
     * punch is stored as manual.
     */
    public function punch(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->payrollProfile;

        if (! $profile || ! $profile->is_attendance_subject) {
            abort(403);
        }

        if (! $profile->can_remote_attendance) {
            throw ValidationException::withMessages([
                'type' => 'Tu asistencia remota no está habilitada. Contacta al administrador.',
            ]);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'method' => ['nullable', 'string', 'in:face,pin'],
            'pin' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'string', 'max:2000000'],
        ]);

        $settings = PayrollSetting::current();
        $faceVerification = $settings->face_recognition_enabled && $this->faceRecognition->isConfigured();
        $identifierMethod = AttendanceLog::IDENTIFIER_MANUAL;
        $similarity = null;
        $method = $validated['method'] ?? null;

        if ($method === 'pin') {
            $this->ensureRemotePinIsValid($profile, $validated['pin'] ?? null);
            $identifierMethod = AttendanceLog::IDENTIFIER_PIN;
        } elseif ($faceVerification) {
            if (empty($validated['photo'])) {
                throw ValidationException::withMessages([
                    'photo' => 'Se requiere la foto de tu rostro para registrar la asistencia remota.',
                ]);
            }

            try {
                $match = $this->faceRecognition->search($validated['photo']);
            } catch (\Throwable $exception) {
                Log::warning('Remote face verification failed.', ['error' => $exception->getMessage()]);

                throw ValidationException::withMessages([
                    'photo' => 'No se pudo procesar tu rostro. Intenta de nuevo con mejor iluminación.',
                ]);
            }

            if (! $match || (int) $match['external_image_id'] !== $user->id) {
                throw ValidationException::withMessages([
                    'photo' => 'El rostro no coincide con tu registro facial. Intenta de nuevo o contacta al administrador.',
                ]);
            }

            $identifierMethod = AttendanceLog::IDENTIFIER_FACE;
            $similarity = $match['similarity'];
        } elseif ($method === 'face') {
            throw ValidationException::withMessages([
                'method' => 'El reconocimiento facial no está activo. Intenta con tu PIN.',
            ]);
        }

        $log = $this->registerPunchAction->execute($user, [
            'type' => $validated['type'],
            'source' => AttendanceLog::SOURCE_REMOTE,
            'identifier_method' => $identifierMethod,
            'face_similarity' => $similarity,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'photo' => $identifierMethod === AttendanceLog::IDENTIFIER_FACE ? ($validated['photo'] ?? null) : null,
        ]);

        return response()->json([
            'type' => $log->type,
            'type_label' => $log->type_label,
            'punched_at' => $log->punched_at->format('H:i'),
            'date_label' => $log->punched_at->format('d/m/Y'),
            'identifier_method' => $identifierMethod,
            'similarity' => $similarity !== null ? round($similarity, 2) : null,
            'suggested_next' => $this->suggestedNextType($user),
            'suggested_next_label' => AttendanceLog::TYPES[$this->suggestedNextType($user)] ?? null,
        ]);
    }

    /**
     * The remote pin must be active and match the collaborator's own kiosk pin.
     */
    private function ensureRemotePinIsValid(PayrollProfile $profile, ?string $pin): void
    {
        if (! PayrollSetting::current()->kiosk_pin_fallback_enabled) {
            throw ValidationException::withMessages([
                'pin' => 'El registro con PIN no está activo. Usa tu rostro o pide ayuda al administrador.',
            ]);
        }

        if ($profile->kiosk_pin === null) {
            throw ValidationException::withMessages([
                'pin' => 'Aún no tienes un PIN configurado. Pide ayuda al administrador.',
            ]);
        }

        if ($pin === null || $pin === '' || ! Hash::check($pin, $profile->kiosk_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN incorrecto. Intenta de nuevo.',
            ]);
        }
    }

    /**
     * Preview of a vacation request: working days of the selected range and
     * the live balance, so the request form can show the impact before the
     * collaborator sends the request.
     */
    public function vacationPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->payrollProfile;

        if (! $profile || ! $profile->is_attendance_subject) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $days = $this->vacationService->workingDaysFor(
            $user,
            CarbonImmutable::parse($validated['start_date']),
            CarbonImmutable::parse($validated['end_date']),
        );

        $balance = $this->vacationService->balanceFor($user);
        $availableDays = (float) $balance['available_days'];

        return response()->json([
            'days' => $days,
            'available_days' => $availableDays,
            'minimum_days' => (float) PayrollSetting::current()->vacation_min_days_to_request,
            'exceeds_balance' => $days > $availableDays,
        ]);
    }

    private function suggestedNextType(User $user): ?string
    {
        $lastPunch = AttendanceLog::forUser($user->id)
            ->onDate(CarbonImmutable::today()->toDateString())
            ->orderByDesc('punched_at')
            ->first();

        $lastType = $lastPunch?->type;
        $order = array_keys(AttendanceLog::TYPES);
        $currentIndex = $lastType ? array_search($lastType, $order, true) : false;

        return $currentIndex === false
            ? $order[0]
            : ($order[$currentIndex + 1] ?? null);
    }

    /**
     * Date range of the history table: the open payroll period that contains
     * today. A period closed early or a next period opened in advance does
     * not move the range; the weekly rollover updates it every Monday.
     * Without an open period the current Monday to Sunday week is used.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function currentPeriodRange(): array
    {
        $today = CarbonImmutable::today();

        $period = PayrollPeriod::query()
            ->where('status', PayrollPeriod::STATUS_OPEN)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($period) {
            return [
                CarbonImmutable::parse($period->start_date->toDateString()),
                CarbonImmutable::parse($period->end_date->toDateString()),
            ];
        }

        $weekStart = $today->startOfWeek();

        return [$weekStart, $weekStart->addDays(6)];
    }

    private function mapDay(AttendanceDaySummary $summary): array
    {
        return [
            'date' => $summary->date->toDateString(),
            'date_label' => $summary->date->translatedFormat('D d/m'),
            'status' => $summary->status,
            'status_label' => $summary->statusLabel(),
            'worked_minutes' => $summary->workedMinutes,
            'expected_minutes' => $summary->expectedMinutes,
            'late_minutes' => $summary->lateMinutes,
            'late_ignored' => $summary->lateIgnored,
            'overtime_minutes' => $summary->overtimeMinutes,
            'first_in' => $summary->firstIn?->format('H:i'),
            'lunch_start' => $summary->lunchStart?->format('H:i'),
            'lunch_end' => $summary->lunchEnd?->format('H:i'),
            'last_out' => $summary->lastOut?->format('H:i'),
            'is_working' => $summary->isWorking,
            'is_paused' => $summary->isPaused,
            'has_schedule' => $summary->hasSchedule,
            'shift_name' => $summary->shift?->name,
            'incident_type' => $summary->incidentType,
            'incident_type_key' => $summary->incidentTypeKey,
            'holiday_name' => $summary->holidayName,
            'notes' => $summary->notes,
            'punches' => $summary->punches
                ->map(fn (AttendanceLog $log) => [
                    'id' => $log->id,
                    'type' => $log->type,
                    'type_label' => $log->type_label,
                    'time' => $log->punched_at->format('H:i'),
                    'source' => $log->source,
                    'source_label' => $log->sourceLabel(),
                    'identifier_method' => $log->identifier_method,
                    'capture_url' => $log->capture_url,
                    'latitude' => $log->latitude,
                    'longitude' => $log->longitude,
                ])
                ->values()
                ->all(),
        ];
    }
}
