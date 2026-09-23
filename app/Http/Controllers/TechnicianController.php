<?php

namespace App\Http\Controllers;

use App\Actions\Payroll\AssignUserShiftAction;
use App\Actions\Payroll\EnrollProfilePhotoAction;
use App\Actions\Payroll\SyncPayrollProfileAction;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Technician;
use App\Models\TechnicianBankAccount;
use App\Models\TechnicianPayment;
use App\Models\TechnicianSpecialty;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Media\ImageOptimizerService;
use App\Services\Payroll\ScheduleResolverService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TechnicianController extends Controller
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
        private readonly SyncPayrollProfileAction $syncPayrollProfileAction,
        private readonly EnrollProfilePhotoAction $enrollProfilePhotoAction,
        private readonly AssignUserShiftAction $assignUserShiftAction,
        private readonly ScheduleResolverService $scheduleResolver,
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $trashed = $request->boolean('trashed');

        $technicians = Technician::with(['user' => function ($q) use ($trashed) {
            if ($trashed) {
                $q->onlyTrashed();
            }
        }])
            ->whereHas('user', function ($q) use ($trashed) {
                if ($trashed) {
                    $q->onlyTrashed();
                } else {
                    $q->whereNull('deleted_at');
                }
            })
            ->filter($request->only('search', 'specialty', 'state'))
            ->when($request->filled('is_internal'), fn ($q) => $q->where('is_internal', $request->boolean('is_internal')))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Return JSON for AJAX requests (e.g. deposit form technician search)
        if ($request->expectsJson()) {
            return response()->json($technicians);
        }

        // Obtener listas únicas para los filtros del frontend
        $states = Technician::select('state')
            ->distinct()
            ->whereNotNull('state')
            ->orderBy('state')
            ->pluck('state');

        $specialties = TechnicianSpecialty::active()->orderBy('name')->pluck('name');

        return Inertia::render('Technicians/Index', [
            'technicians' => $technicians,
            'filters' => $request->only(['search', 'perPage', 'specialty', 'state', 'trashed', 'is_internal']),
            'states' => $states,
            'specialties' => $specialties,
        ]);
    }

    public function create()
    {
        // Pasamos las especialidades también al formulario de creación si lo necesitas
        return Inertia::render('Technicians/Create', [
            'availableSpecialties' => TechnicianSpecialty::active()->orderBy('name')->pluck('name'),
            'shifts' => Shift::optionList(),
            'currentShiftId' => null,
        ]);
    }

    public function store(Request $request)
    {
        // CAMBIO: Dejamos como "required" solo name y phone (como en el quickStore)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users',
            'photo' => 'nullable|image|max:2048',
            'phone' => 'required|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'is_internal' => 'boolean',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'colony' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'coverage_radius_km' => 'nullable|integer|min:1',
            'specialties' => 'nullable|array',
            'specialties.*' => 'string',
            'level' => 'nullable|string|in:'.implode(',', Technician::LEVELS),
            'legal_name' => 'nullable|string',
            'rfc' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'clabe' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'tax_file' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'rating_avg' => 'nullable|numeric|min:0|max:5',
            ...$this->payrollRules($request),
        ], $this->payrollMessages());

        // Only internal technicians are paid through payroll: external collaborators
        // can never be saved as payroll subjects.
        if (array_key_exists('is_payroll_subject', $validated) && ! ($validated['is_internal'] ?? false)) {
            $validated['is_payroll_subject'] = false;
        }

        $payrollProfile = $this->syncPayrollProfileAction->sanitizeFor($request->user(), $validated);

        DB::transaction(function () use ($validated, $request, $payrollProfile) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null, // CORRECCIÓN: Agregar ?? null
                'password' => Hash::make(Str::random(12)),
                'is_active' => false,
            ]);

            $optimizedPhotoPath = $request->hasFile('photo')
                ? $this->imageOptimizer->optimize($request->file('photo'))
                : null;

            if ($optimizedPhotoPath !== null) {
                $user->updateProfilePhoto(new \Illuminate\Http\UploadedFile(
                    $optimizedPhotoPath,
                    $request->file('photo')->getClientOriginalName(),
                    $request->file('photo')->getMimeType(),
                    null,
                    true
                ));
            }

            $technician = Technician::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
                'secondary_phone' => $validated['secondary_phone'] ?? null, // CORRECCIONES
                'is_internal' => $validated['is_internal'] ?? false,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'colony' => $validated['colony'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
                'coverage_radius_km' => $validated['coverage_radius_km'] ?? 10,
                'specialties' => $validated['specialties'] ?? [], // CORRECCIÓN CLAVE
                'level' => $validated['level'] ?? 'Encargado',
                'legal_name' => $validated['legal_name'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account' => $validated['bank_account'] ?? null,
                'clabe' => $validated['clabe'] ?? null,
                'status' => 'En revisión',
                'internal_notes' => $validated['internal_notes'] ?? null,
                'rating_avg' => $validated['rating_avg'] ?? 0,
            ]);

            $this->syncPayrollProfileAction->execute($technician->user, $payrollProfile);

            $this->assignUserShiftAction->executeFromForm($request, $technician->user, $validated);

            // The profile photo doubles as the face reference of the attendance kiosk.
            if ($optimizedPhotoPath !== null) {
                $this->enrollProfilePhotoAction->execute($technician->user, $optimizedPhotoPath, $request->user());
            }

            if ($request->hasFile('tax_file')) {
                $file = $request->file('tax_file');
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $technician->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('fiscal_documents');
                } else {
                    $technician->addMediaFromRequest('tax_file')
                        ->toMediaCollection('fiscal_documents');
                }
            }
        });

        return redirect()->route('technicians.index')->with('success', 'Técnico registrado correctamente.');
    }

    public function show(Technician $technician)
    {
        $technician->load([
            'user' => fn ($q) => $q->withTrashed(),
            'user.payrollProfile',
            'media',
            'bankAccounts.media',
        ]);

        $historyQuery = Ticket::with(['budget.customer', 'tasks'])
            ->where(function ($query) use ($technician) {
                $query->whereJsonContains('technicians', (string) $technician->user_id)
                    ->orWhereJsonContains('technicians', (int) $technician->user_id)
                    ->orWhereHas('tasks', function ($q) use ($technician) {
                        $q->where('user_id', $technician->user_id);
                    });
            });

        $tickets = $historyQuery->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $payments = TechnicianPayment::where('user_id', $technician->user_id)
            ->with(['budget.customer', 'media'])
            ->orderBy('payment_date', 'desc')
            ->get();

        $totalTickets = $historyQuery->count();
        $completedTickets = (clone $historyQuery)->whereIn('status', ['Ejecutado', 'Facturado', 'Pagado'])->count();
        $completionRate = $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100) : 0;
        $totalEarnings = $payments->sum('amount');

        return Inertia::render('Technicians/Show', [
            'technician' => $technician,
            'tickets' => $tickets,
            'payments' => $payments,
            // Effective shift today (individual, department or rotation).
            'currentShift' => $technician->user
                ? $this->scheduleResolver->resolveFor($technician->user, CarbonImmutable::today())?->shift
                : null,
            'kpis' => [
                'total_tickets' => $totalTickets,
                'completion_rate' => $completionRate,
                'total_earnings' => $totalEarnings,
            ],
        ]);
    }

    public function edit(Technician $technician)
    {
        return Inertia::render('Technicians/Edit', [
            'technician' => $technician->load(['user.payrollProfile', 'bankAccounts.media']),
            'availableSpecialties' => TechnicianSpecialty::active()->orderBy('name')->pluck('name'),
            'shifts' => Shift::optionList(),
            'currentShiftId' => ShiftAssignment::currentFor($technician->user)?->shift_id,
        ]);
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($technician->user_id)],
            'photo' => 'nullable|image|max:2048',
            'phone' => 'required|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'is_internal' => 'boolean',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'colony' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'coverage_radius_km' => 'nullable|integer',
            'specialties' => 'nullable|array',
            'specialties.*' => 'string',
            'level' => 'nullable|string|in:'.implode(',', Technician::LEVELS),
            'legal_name' => 'nullable|string',
            'rfc' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'clabe' => 'nullable|string',
            'status' => 'required|string',
            'internal_notes' => 'nullable|string',
            'rating_avg' => 'nullable|numeric|min:0|max:5',
            'tax_file' => 'nullable|file|mimes:pdf,jpg,png|max:5120', // CORRECCIÓN 1: Faltaba validar el archivo
            ...$this->payrollRules($request, $technician),
        ], $this->payrollMessages());

        // Turning the technician into an external one removes them from payroll.
        if (array_key_exists('is_payroll_subject', $validated) && ! ($validated['is_internal'] ?? $technician->is_internal)) {
            $validated['is_payroll_subject'] = false;
        }

        $payrollProfile = $this->syncPayrollProfileAction->sanitizeFor($request->user(), $validated);

        DB::transaction(function () use ($validated, $request, $technician, $payrollProfile) {
            $technician->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
            ]);

            $optimizedPhotoPath = $request->hasFile('photo')
                ? $this->imageOptimizer->optimize($request->file('photo'))
                : null;

            if ($optimizedPhotoPath !== null) {
                $technician->user->updateProfilePhoto(new \Illuminate\Http\UploadedFile(
                    $optimizedPhotoPath,
                    $request->file('photo')->getClientOriginalName(),
                    $request->file('photo')->getMimeType(),
                    null,
                    true
                ));
            }

            $technician->update([
                'phone' => $validated['phone'],
                'secondary_phone' => $validated['secondary_phone'] ?? null,
                'is_internal' => $validated['is_internal'] ?? false,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'colony' => $validated['colony'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
                'coverage_radius_km' => $validated['coverage_radius_km'] ?? $technician->coverage_radius_km,
                'specialties' => $validated['specialties'] ?? [],
                'level' => $validated['level'] ?? 'Encargado',
                'legal_name' => $validated['legal_name'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account' => $validated['bank_account'] ?? null,
                'clabe' => $validated['clabe'] ?? null,
                'status' => $validated['status'] ?? $technician->status,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'rating_avg' => $validated['rating_avg'] ?? $technician->rating_avg,
            ]);

            $this->syncPayrollProfileAction->execute($technician->user, $payrollProfile);

            $this->assignUserShiftAction->executeFromForm($request, $technician->user, $validated);

            // The profile photo doubles as the face reference of the attendance kiosk.
            if ($optimizedPhotoPath !== null) {
                $this->enrollProfilePhotoAction->execute($technician->user, $optimizedPhotoPath, $request->user());
            }

            // CORRECCIÓN 2: Lógica para guardar la constancia fiscal si se adjuntó
            if ($request->hasFile('tax_file')) {
                // Borramos el documento anterior si existía para no llenar el servidor de archivos viejos
                $technician->clearMediaCollection('fiscal_documents');

                $file = $request->file('tax_file');
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $technician->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('fiscal_documents');
                } else {
                    $technician->addMediaFromRequest('tax_file')
                        ->toMediaCollection('fiscal_documents');
                }
            }
        });

        return redirect()->route('technicians.show', $technician->id)->with('success', 'Perfil actualizado.');
    }

    /**
     * Optional payroll and attendance fields edited from the technician form.
     *
     * @return array<string, array<int, mixed>>
     */
    private function payrollRules(Request $request, ?Technician $technician = null): array
    {
        // Only an internal technician marked as payroll subject needs a
        // schedule: its daily hours feed the minute rate used by overtime and
        // late discounts. (External ones are forced out of payroll.)
        $isInternal = $request->has('is_internal')
            ? $request->boolean('is_internal')
            : (bool) $technician?->is_internal;

        $shiftRequired = $request->user()?->can('payroll.profiles.manage')
            && $isInternal
            && $request->boolean('is_payroll_subject');

        return [
            'employee_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'daily_salary' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999'],
            'daily_hours' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:24'],
            // Only internal technicians can be payroll subjects (see the is_internal guards).
            'is_payroll_subject' => ['sometimes', 'boolean'],
            'is_attendance_subject' => ['sometimes', 'boolean'],
            'can_remote_attendance' => ['sometimes', 'boolean'],
            'kiosk_pin' => ['nullable', 'string', 'min:4', 'max:12', 'regex:/^[0-9]+$/'],
            'shift_id' => [
                $shiftRequired ? 'required' : 'nullable',
                'integer',
                'exists:shifts,id',
            ],
        ];
    }

    /**
     * Custom messages of the optional payroll fields.
     *
     * @return array<string, string>
     */
    private function payrollMessages(): array
    {
        return [
            'shift_id.required' => 'Selecciona un horario para el técnico sujeto a nómina.',
        ];
    }

    public function updateStatus(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Activo,Inactivo,En revisión,Vetado,Eliminado',
        ]);

        $technician->update(['status' => $validated['status']]);

        return back()->with('success', 'Estatus del técnico actualizado.');
    }

    public function updateRating(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'rating' => 'required|numeric|min:0|max:5',
        ]);

        $technician->update(['rating_avg' => $validated['rating']]);

        return back()->with('success', 'Calificación actualizada.');
    }

    // --- NUEVO MÉTODO: REGISTRO RÁPIDO DESDE TICKET ---
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'is_internal' => 'boolean', // NUEVO CAMPO ACEPTADO
            'level' => 'nullable|string|in:'.implode(',', Technician::LEVELS),
            'state' => 'nullable|string|max:255',
        ]);

        $user = null;

        DB::transaction(function () use ($validated, &$user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => null,
                'password' => Hash::make(Str::random(12)),
                'is_active' => true,
            ]);

            Technician::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
                'is_internal' => $validated['is_internal'] ?? false, // APLICADO AQUÍ
                'level' => $validated['level'] ?? 'Encargado',
                'state' => $validated['state'] ?? null,
                'status' => 'Activo',
                'rating_avg' => 0,
                'coverage_radius_km' => 10,
            ]);

            $user->load('technician');
        });

        return response()->json([
            'user' => $user,
            'message' => 'Técnico registrado rápidamente.',
        ], 201);
    }

    public function deleteMedia(Technician $technician, $mediaId)
    {
        $media = $technician->media()->findOrFail($mediaId);
        $media->delete();

        return back()->with('success', 'Documento eliminado.');
    }

    // --- BANK ACCOUNTS ---

    public function storeBankAccount(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:100',
            'card_owner_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'card_number' => 'nullable|string|max:50',
            'clabe' => 'nullable|string|max:50',
            'branch_number' => 'nullable|string|max:50',
            'qr_image' => 'nullable|image|max:2048',
        ]);

        // If this is the first account, make it favorite by default
        $isFavorite = $technician->bankAccounts()->count() === 0;

        $account = $technician->bankAccounts()->create([
            'bank_name' => $validated['bank_name'] ?? null,
            'card_owner_name' => $validated['card_owner_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'card_number' => $validated['card_number'] ?? null,
            'clabe' => $validated['clabe'] ?? null,
            'branch_number' => $validated['branch_number'] ?? null,
            'is_favorite' => $isFavorite,
        ]);

        if ($request->hasFile('qr_image')) {
            $account->addMediaFromRequest('qr_image')
                ->toMediaCollection('bank_qr');
        }

        return back()->with('success', 'Cuenta bancaria agregada.');
    }

    public function updateBankAccount(Request $request, Technician $technician, TechnicianBankAccount $account)
    {
        $validated = $request->validate([
            'bank_name' => 'nullable|string|max:100',
            'card_owner_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'card_number' => 'nullable|string|max:50',
            'clabe' => 'nullable|string|max:50',
            'branch_number' => 'nullable|string|max:50',
            'qr_image' => 'nullable|image|max:2048',
        ]);

        $account->update([
            'bank_name' => $validated['bank_name'] ?? null,
            'card_owner_name' => $validated['card_owner_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'card_number' => $validated['card_number'] ?? null,
            'clabe' => $validated['clabe'] ?? null,
            'branch_number' => $validated['branch_number'] ?? null,
        ]);

        if ($request->hasFile('qr_image')) {
            $account->clearMediaCollection('bank_qr');
            $account->addMediaFromRequest('qr_image')
                ->toMediaCollection('bank_qr');
        }

        return back()->with('success', 'Cuenta bancaria actualizada.');
    }

    public function destroyBankAccount(Technician $technician, TechnicianBankAccount $account)
    {
        $wasFavorite = $account->is_favorite;
        $account->delete();

        // If the deleted account was favorite, make the first remaining one favorite
        if ($wasFavorite) {
            $next = $technician->bankAccounts()->first();
            if ($next) {
                $next->update(['is_favorite' => true]);
            }
        }

        return back()->with('success', 'Cuenta bancaria eliminada.');
    }

    public function setFavoriteBankAccount(Technician $technician, TechnicianBankAccount $account)
    {
        $technician->bankAccounts()->update(['is_favorite' => false]);
        $account->update(['is_favorite' => true]);

        return back()->with('success', 'Cuenta favorita actualizada.');
    }

    public function destroy(Request $request, Technician $technician)
    {
        // Internal technicians are paid through payroll, so their dismissal asks for
        // a termination date: they keep their record and stop appearing in the payroll
        // periods that start after it (same behaviour as collaborators in Users).
        if ($technician->is_internal) {
            $validated = $request->validate([
                'termination_date' => ['nullable', 'date'],
            ]);

            $terminationDate = CarbonImmutable::parse($validated['termination_date'] ?? now()->toDateString())->toDateString();
            $profile = $technician->user->payrollProfile;

            if ($profile?->hire_date && $terminationDate < $profile->hire_date->toDateString()) {
                throw ValidationException::withMessages([
                    'termination_date' => 'La fecha de baja no puede ser anterior a la fecha de ingreso ('.$profile->hire_date->format('d/m/Y').').',
                ]);
            }

            DB::transaction(function () use ($technician, $terminationDate) {
                $profile = $technician->user->payrollProfile ?? $technician->user->payrollProfile()->make();
                $profile->termination_date = $terminationDate;
                $profile->save();

                $technician->user->update(['is_active' => false]);
                $technician->update(['status' => 'Inactivo']);
            });

            return redirect()->route('technicians.index')
                ->with('success', 'Técnico dado de baja el '.CarbonImmutable::parse($terminationDate)->format('d/m/Y').'. Ya no aparecerá en los periodos de nómina posteriores y puede reactivarse desde el listado.');
        }

        $technician->update(['status' => 'Eliminado']);
        $technician->user->delete(); // Soft delete gracias al trait SoftDeletes en User

        return redirect()->route('technicians.index')
            ->with('success', 'Técnico dado de baja correctamente. Su historial y trazabilidad se conservan.');
    }

    public function restore($id)
    {
        $technician = Technician::where('id', $id)->firstOrFail();
        $user = User::withTrashed()->findOrFail($technician->user_id);
        $user->restore();
        // Reactivation clears the payroll termination date so the collaborator
        // returns to the payroll and can register attendance again.
        $user->update(['is_active' => true]);
        $user->payrollProfile?->update(['termination_date' => null]);
        $technician->update(['status' => 'Activo']);

        return redirect()->route('technicians.index')
            ->with('success', 'Técnico reactivado correctamente.');
    }
}
