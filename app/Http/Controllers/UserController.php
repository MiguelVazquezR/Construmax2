<?php

namespace App\Http\Controllers;

use App\Actions\Payroll\AssignUserShiftAction;
use App\Actions\Payroll\EnrollProfilePhotoAction;
use App\Actions\Payroll\SyncPayrollProfileAction;
use App\Models\FaceEnrollment;
use App\Models\PayrollSetting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\VacationAdjustment;
use App\Models\VacationRequest;
use App\Services\Media\ImageOptimizerService;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use App\Services\Payroll\VacationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request; // Importar modelo Role
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly SyncPayrollProfileAction $syncPayrollProfileAction,
        private readonly FaceRecognitionService $faceRecognition,
        private readonly EnrollProfilePhotoAction $enrollProfilePhotoAction,
        private readonly ImageOptimizerService $imageOptimizer,
        private readonly VacationService $vacationService,
        private readonly AssignUserShiftAction $assignUserShiftAction,
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);

        return Inertia::render('Users/Index', [
            'users' => User::with(['employee', 'roles'])
                ->where('id', '!=', 1) // Excluir al super admin
                ->whereDoesntHave('technician') // Solo usuarios sin técnico asociado
                ->filter($request->only('search'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    public function create()
    {
        // Enviamos los roles disponibles a la vista
        return Inertia::render('Users/Create', [
            'roles' => Role::all(),
            'faceRecognitionEnabled' => $this->faceRecognitionEnabled(),
            'shifts' => Shift::optionList(),
            'currentShiftId' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
            'roles' => 'required|array|min:1', // Validar que se envíe al menos un rol
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ...$this->payrollRules(),
        ]);

        // Only the fields the acting user is allowed to change (permissions aware)
        $payrollProfile = $this->syncPayrollProfileAction->sanitizeFor($request->user(), $validated);

        $photoStatus = DB::transaction(function () use ($validated, $payrollProfile, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            // Asignar roles (Spatie)
            $user->assignRole($validated['roles']);

            $user->employee()->create([
                'department' => $validated['department'],
                'position' => $validated['position'],
                'phone' => $validated['phone'],
            ]);

            $this->syncPayrollProfileAction->execute($user, $payrollProfile);

            $this->assignUserShiftAction->executeFromForm($request, $user, $validated);

            return $request->hasFile('photo')
                ? $this->applyProfilePhoto($user, $request->file('photo'), $request->user())
                : null;
        });

        return redirect()->route('users.index')
            ->with('success', $this->userSavedMessage('Usuario creado y roles asignados correctamente.', $photoStatus));
    }

    public function show(Request $request, User $user)
    {
        $user->load([
            'employee',
            'payrollProfile',
            'roles',
            'ticketsAsSeller' => function ($query) {
                $query->orderBy('id', 'desc')
                    ->with('branch');
            },
        ]);

        return Inertia::render('Users/Show', [
            'user' => $user,
            'faceEnrollment' => [
                'activeCount' => FaceEnrollment::where('user_id', $user->id)->where('status', FaceEnrollment::STATUS_ACTIVE)->count(),
                'configured' => $this->faceRecognition->isConfigured(),
            ],
            'vacation' => $this->vacationPayload($request, $user),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->load(['employee', 'payrollProfile', 'roles']),
            'roles' => Role::all(), // Enviamos roles para la edición
            'faceRecognitionEnabled' => $this->faceRecognitionEnabled(),
            'shifts' => Shift::optionList(),
            'currentShiftId' => ShiftAssignment::currentFor($user)?->shift_id,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => ['nullable', Rules\Password::defaults()],
            'roles' => 'required|array|min:1', // Roles requeridos en edición también
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ...$this->payrollRules(),
        ]);

        // Only the fields the acting user is allowed to change (permissions aware)
        $payrollProfile = $this->syncPayrollProfileAction->sanitizeFor($request->user(), $validated);

        $photoStatus = DB::transaction(function () use ($validated, $user, $payrollProfile, $request) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // Sincronizar roles (reemplaza los anteriores por los nuevos)
            $user->syncRoles($validated['roles']);

            $user->employee()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department' => $validated['department'],
                    'position' => $validated['position'],
                    'phone' => $validated['phone'],
                ]
            );

            $this->syncPayrollProfileAction->execute($user, $payrollProfile);

            $this->assignUserShiftAction->executeFromForm($request, $user, $validated);

            return $request->hasFile('photo')
                ? $this->applyProfilePhoto($user, $request->file('photo'), $request->user())
                : null;
        });

        return redirect()->route('users.index')
            ->with('success', $this->userSavedMessage('Usuario actualizado exitosamente.', $photoStatus));
    }

    /**
     * Store the uploaded profile photo and, when the facial recognition is
     * active, index it as the face reference used by the attendance kiosk.
     */
    private function applyProfilePhoto(User $user, UploadedFile $photo, ?User $actor): string
    {
        $optimizedPath = $this->imageOptimizer->optimize($photo);

        $status = $this->enrollProfilePhotoAction->execute($user, $optimizedPath, $actor);

        $user->updateProfilePhoto(new UploadedFile(
            $optimizedPath,
            $photo->getClientOriginalName(),
            $photo->getMimeType(),
            null,
            true
        ));

        return $status;
    }

    /**
     * Append the facial enrollment outcome to the saved message when relevant.
     */
    private function userSavedMessage(string $baseMessage, ?string $photoStatus): string
    {
        return match ($photoStatus) {
            EnrollProfilePhotoAction::RESULT_ENROLLED => $baseMessage.' La foto se registró como referencia facial.',
            EnrollProfilePhotoAction::RESULT_FAILED => $baseMessage.' No se pudo detectar el rostro en la foto: regístralo desde la ficha del usuario.',
            default => $baseMessage,
        };
    }

    private function faceRecognitionEnabled(): bool
    {
        return (bool) PayrollSetting::current()->face_recognition_enabled && $this->faceRecognition->isConfigured();
    }

    /**
     * Vacation information of the collaborator shown in the "Información
     * general" tab: the live balance, the manual movements registered by the
     * payroll team and the latest requests.
     */
    private function vacationPayload(Request $request, User $user): array
    {
        $adjustments = VacationAdjustment::query()
            ->forUser($user->id)
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VacationAdjustment $adjustment) => $adjustment->toPayload())
            ->values()
            ->all();

        $requests = VacationRequest::query()
            ->forUser($user->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (VacationRequest $vacationRequest) => [
                'id' => $vacationRequest->id,
                'start_date' => $vacationRequest->start_date?->toDateString(),
                'end_date' => $vacationRequest->end_date?->toDateString(),
                'days' => (float) $vacationRequest->days,
                'status' => $vacationRequest->status,
                'status_label' => $vacationRequest->statusLabel(),
            ])
            ->values()
            ->all();

        return [
            'can_manage' => $request->user()->can('payroll.vacations.manage'),
            'can_view_module' => $request->user()->can('payroll.vacations.manage')
                || $request->user()->can('payroll.vacations.approve'),
            'balance' => $this->vacationService->balanceFor($user),
            'adjustments' => $adjustments,
            'requests' => $requests,
        ];
    }

    /**
     * Optional payroll and attendance fields edited from the user form.
     *
     * @return array<string, array<int, mixed>>
     */
    private function payrollRules(): array
    {
        return [
            'employee_number' => ['nullable', 'string', 'max:50'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'daily_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'daily_hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'is_payroll_subject' => ['sometimes', 'boolean'],
            'is_attendance_subject' => ['sometimes', 'boolean'],
            'can_remote_attendance' => ['sometimes', 'boolean'],
            'kiosk_pin' => ['nullable', 'string', 'min:4', 'max:12', 'regex:/^[0-9]+$/'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ];
    }

    /**
     * Clean up all foreign key references to the given user IDs before deletion.
     *
     * Strategy per table:
     *   - Nullable FK          → set user_id = null (e.g. ticket_tasks)
     *   - NOT NULL, owns data  → DELETE the row (e.g. calendars, calendar_participants, tech_payments)
     *   - NOT NULL, important  → reassign to admin (id=1) (e.g. budgets)
     */
    private function nullifyUserReferences(array $ids): void
    {
        // Nullable FK — safe to just unlink
        DB::table('ticket_tasks')->whereIn('user_id', $ids)->update(['user_id' => null]);

        // NOT NULL — delete the row (acceptable data loss for mistakenly-created users)
        DB::table('calendars')->whereIn('user_id', $ids)->delete();
        DB::table('calendar_participants')->whereIn('user_id', $ids)->delete();
        DB::table('technician_payments')->whereIn('user_id', $ids)->delete();
        DB::table('field_work_schedules')->whereIn('user_id', $ids)->delete();

        // NOT NULL but important data — reassign to admin (id=1)
        DB::table('budgets')->whereIn('user_id', $ids)->update(['user_id' => 1]);
    }

    public function destroy(User $user)
    {
        if ($user->id === 1) {
            return back()->with('error', 'No se puede eliminar al super administrador.');
        }

        $this->nullifyUserReferences([$user->id]);

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Exclude super admin (id=1) from bulk deletion
        $ids = array_values(array_filter($validated['ids'], fn ($id) => $id !== 1));

        if (empty($ids)) {
            return back()->with('error', 'No se puede eliminar al super administrador.');
        }

        $this->nullifyUserReferences($ids);

        User::whereIn('id', $ids)->delete();

        return back()->with('success', count($ids).' usuarios eliminados correctamente.');
    }

    /**
     * Dismiss or reactivate a user. Dismissing stores the termination date in
     * the payroll profile so the collaborator leaves the payroll from that date
     * on; reactivating clears it so they come back.
     */
    public function toggleStatus(Request $request, User $user)
    {
        if (! $request->user()->can('users.toggle-status')) {
            abort(403);
        }

        if ($user->id === 1) {
            return back()->with('error', 'No se puede dar de baja al super administrador.');
        }

        if (! $user->is_active) {
            $user->is_active = true;
            $user->save();
            $user->payrollProfile?->update(['termination_date' => null]);

            return back()->with('success', 'Usuario activado. Se eliminó su fecha de baja.');
        }

        $validated = $request->validate([
            'termination_date' => ['nullable', 'date'],
        ]);

        $terminationDate = CarbonImmutable::parse($validated['termination_date'] ?? now()->toDateString())->toDateString();
        $profile = $user->payrollProfile;

        if ($profile?->hire_date && $terminationDate < $profile->hire_date->toDateString()) {
            throw ValidationException::withMessages([
                'termination_date' => 'La fecha de baja no puede ser anterior a la fecha de ingreso ('.$profile->hire_date->format('d/m/Y').').',
            ]);
        }

        $user->is_active = false;
        $user->save();
        $profile?->update(['termination_date' => $terminationDate]);

        return back()->with('success', $profile
            ? 'Usuario dado de baja el '.$profile->termination_date->format('d/m/Y').'. Ya no aparecerá en los periodos de nómina posteriores.'
            : 'Usuario dado de baja.');
    }
}
