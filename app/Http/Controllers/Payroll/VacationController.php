<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RequestVacationAction;
use App\Actions\Payroll\ReviewVacationRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ReviewVacationRequest;
use App\Http\Requests\Payroll\StoreVacationRequest;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Payroll\VacationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VacationController extends Controller
{
    public function __construct(
        private readonly VacationService $vacationService,
        private readonly RequestVacationAction $requestVacationAction,
        private readonly ReviewVacationRequestAction $reviewVacationRequestAction,
    ) {}

    public function index(Request $request): Response
    {
        if (! $this->canManage($request) && ! $request->user()->can('payroll.vacations.approve')) {
            abort(403);
        }

        $requests = VacationRequest::query()
            ->with(['user:id,name', 'reviewer:id,name', 'requestedBy:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        $selectedUserId = $request->filled('user_id') ? $request->integer('user_id') : null;
        $selectedUser = $selectedUserId ? User::find($selectedUserId) : null;

        return Inertia::render('Payroll/Vacations/Index', [
            'requests' => $requests,
            'users' => $this->attendanceUsers(),
            'statuses' => VacationRequest::STATUSES,
            'balance' => $selectedUser ? $this->vacationService->balanceFor($selectedUser) : null,
            'selectedUserId' => $selectedUserId,
            'filters' => $request->only(['status', 'user_id']),
        ]);
    }

    /**
     * Create a vacation request: the collaborator requests their own days;
     * managers (payroll.vacations.manage) can request on behalf of someone.
     */
    public function store(StoreVacationRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $target = $actor;

        if ($this->canManage($request) && $request->filled('user_id')) {
            $target = User::findOrFail($request->integer('user_id'));
        } elseif (! $actor->payrollProfile?->is_attendance_subject) {
            throw ValidationException::withMessages([
                'user_id' => 'El colaborador no tiene la asistencia habilitada.',
            ]);
        }

        $this->requestVacationAction->execute($target, $request->validated(), $actor);

        return back()->with('success', 'Solicitud de vacaciones enviada.');
    }

    public function approve(ReviewVacationRequest $request, VacationRequest $vacationRequest): RedirectResponse
    {
        $this->reviewVacationRequestAction->execute(
            $vacationRequest,
            $request->user(),
            true,
            $request->validated('notes'),
        );

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function reject(ReviewVacationRequest $request, VacationRequest $vacationRequest): RedirectResponse
    {
        $this->reviewVacationRequestAction->execute(
            $vacationRequest,
            $request->user(),
            false,
            $request->validated('notes'),
        );

        return back()->with('success', 'Solicitud rechazada.');
    }

    /**
     * Cancel a pending request (own request, or any with the manage permission).
     */
    public function cancel(Request $request, VacationRequest $vacationRequest): RedirectResponse
    {
        $actor = $request->user();

        if ($vacationRequest->user_id !== $actor->id && ! $this->canManage($request)) {
            abort(403);
        }

        if (! $vacationRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden cancelar solicitudes pendientes.',
            ]);
        }

        $vacationRequest->update([
            'status' => VacationRequest::STATUS_CANCELLED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Solicitud cancelada.');
    }

    /**
     * Balance of the authenticated collaborator (or of any user with permission).
     */
    public function balance(Request $request): JsonResponse
    {
        $target = $request->user();

        if ($this->canManage($request) && $request->filled('user_id')) {
            $target = User::findOrFail($request->integer('user_id'));
        }

        return response()->json([
            'balance' => $this->vacationService->balanceFor($target),
        ]);
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->can('payroll.vacations.manage');
    }

    private function attendanceUsers()
    {
        return User::query()
            ->whereHas('payrollProfile', fn ($query) => $query->where('is_attendance_subject', true))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
