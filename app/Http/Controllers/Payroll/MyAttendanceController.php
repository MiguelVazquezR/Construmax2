<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RegisterAttendancePunchAction;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\FaceEnrollment;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Payroll\AttendanceDayService;
use App\Services\Payroll\AttendanceDaySummary;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use App\Services\Payroll\VacationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    /**
     * Number of days shown in the recent attendance summary.
     */
    private const RECENT_DAYS = 7;

    public function __construct(
        private readonly AttendanceDayService $attendanceDayService,
        private readonly VacationService $vacationService,
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

        $recentDays = collect(range(0, self::RECENT_DAYS - 1))
            ->map(fn (int $daysAgo) => $this->mapDay(
                $this->attendanceDayService->summaryFor($user, CarbonImmutable::today()->subDays($daysAgo))
            ))
            ->values()
            ->all();

        $punches = AttendanceLog::forUser($user->id)
            ->orderByDesc('punched_at')
            ->limit(20)
            ->get()
            ->map(fn (AttendanceLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'type_label' => $log->type_label,
                'punched_at' => $log->punched_at->format('d/m/Y H:i'),
                'source' => $log->source,
                'source_label' => $log->sourceLabel(),
                'identifier_method' => $log->identifier_method,
                'capture_url' => $log->capture_url,
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
            ])
            ->values()
            ->all();

        $vacationRequests = VacationRequest::query()
            ->forUser($user->id)
            ->orderByDesc('start_date')
            ->limit(20)
            ->get()
            ->map(fn (VacationRequest $vacationRequest) => [
                'id' => $vacationRequest->id,
                'start_date' => $vacationRequest->start_date->toDateString(),
                'end_date' => $vacationRequest->end_date->toDateString(),
                'days' => (float) $vacationRequest->days,
                'status' => $vacationRequest->status,
                'status_label' => $vacationRequest->statusLabel(),
                'review_notes' => $vacationRequest->review_notes,
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
                'total_net' => (float) $payslip->total_net,
                'days_paid' => (float) $payslip->days_paid,
                'generated_at' => $payslip->generated_at?->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('Payroll/MyAttendance/Index', [
            'profile' => [
                'employee_number' => $profile->employee_number,
                'can_remote_attendance' => (bool) $profile->can_remote_attendance,
                'hire_date' => $profile->hire_date?->toDateString(),
            ],
            'today' => $this->mapDay($today),
            'suggestedNextType' => $this->suggestedNextType($user),
            'recentDays' => $recentDays,
            'punches' => $punches,
            'vacationBalance' => $this->vacationService->balanceFor($user),
            'vacationRequests' => $vacationRequests,
            'payslips' => $payslips,
            'punchTypes' => AttendanceLog::TYPES,
            'vacationMinDays' => (float) $settings->vacation_min_days_to_request,
            'remoteGeolocationRequired' => (bool) $settings->remote_geolocation_required,
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
     * Register a remote punch with geolocation and (when enabled) the face
     * of the authenticated collaborator as verification.
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'string', 'max:2000000'],
        ]);

        $settings = PayrollSetting::current();
        $identifierMethod = AttendanceLog::IDENTIFIER_MANUAL;
        $similarity = null;

        if ($settings->face_recognition_enabled && $this->faceRecognition->isConfigured()) {
            if (empty($validated['photo'])) {
                throw ValidationException::withMessages([
                    'photo' => 'Se requiere la foto de tu rostro para registrar el marcaje remoto.',
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
        }

        $log = $this->registerPunchAction->execute($user, [
            'type' => $validated['type'],
            'source' => AttendanceLog::SOURCE_REMOTE,
            'identifier_method' => $identifierMethod,
            'face_similarity' => $similarity,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'photo' => $validated['photo'] ?? null,
        ]);

        return response()->json([
            'type' => $log->type,
            'type_label' => $log->type_label,
            'punched_at' => $log->punched_at->format('H:i'),
            'date_label' => $log->punched_at->format('d/m/Y'),
            'similarity' => $similarity !== null ? round($similarity, 2) : null,
            'suggested_next' => $this->suggestedNextType($user),
            'suggested_next_label' => AttendanceLog::TYPES[$this->suggestedNextType($user)] ?? null,
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
            'overtime_minutes' => $summary->overtimeMinutes,
            'first_in' => $summary->firstIn?->format('H:i'),
            'lunch_start' => $summary->lunchStart?->format('H:i'),
            'lunch_end' => $summary->lunchEnd?->format('H:i'),
            'last_out' => $summary->lastOut?->format('H:i'),
            'is_working' => $summary->isWorking,
            'is_paused' => $summary->isPaused,
            'has_schedule' => $summary->hasSchedule,
            'shift_name' => $summary->shift?->name,
        ];
    }
}
