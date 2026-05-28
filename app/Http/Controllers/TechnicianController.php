<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TechnicianPayment;
use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class TechnicianController extends Controller
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);

        // Obtener listas únicas para los filtros del frontend
        $states = Technician::select('state')
            ->distinct()
            ->whereNotNull('state')
            ->orderBy('state')
            ->pluck('state');
        
        // Usamos la constante del Modelo para asegurar consistencia
        $specialties = Technician::SPECIALTIES;

        return Inertia::render('Technicians/Index', [
            'technicians' => Technician::with('user')
                ->filter($request->only('search', 'specialty', 'state'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'perPage', 'specialty', 'state']),
            'states' => $states,
            'specialties' => $specialties 
        ]);
    }

    public function create()
    {
        // Pasamos las especialidades también al formulario de creación si lo necesitas
        return Inertia::render('Technicians/Create', [
            'availableSpecialties' => Technician::SPECIALTIES
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
            'legal_name' => 'nullable|string',
            'rfc' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'clabe' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'tax_file' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'rating_avg' => 'nullable|numeric|min:0|max:5',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null, // CORRECCIÓN: Agregar ?? null
                'password' => Hash::make(Str::random(12)),
                'is_active' => false,
            ]);

            if ($request->hasFile('photo')) {
                $optimizedPath = $this->imageOptimizer->optimize($request->file('photo'));
                $user->updateProfilePhoto(new \Illuminate\Http\UploadedFile(
                    $optimizedPath,
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
                'legal_name' => $validated['legal_name'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account' => $validated['bank_account'] ?? null,
                'clabe' => $validated['clabe'] ?? null,
                'status' => 'En revisión',
                'internal_notes' => $validated['internal_notes'] ?? null,
                'rating_avg' => $validated['rating_avg'] ?? 0,
            ]);

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
        $technician->load(['user', 'media']);
        
        $historyQuery = Ticket::with(['budget.customer', 'tasks'])
            ->where(function($query) use ($technician) {
                $query->whereJsonContains('technicians', (string) $technician->user_id)
                      ->orWhereJsonContains('technicians', (int) $technician->user_id)
                      ->orWhereHas('tasks', function($q) use ($technician) {
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
            'kpis' => [
                'total_tickets' => $totalTickets,
                'completion_rate' => $completionRate,
                'total_earnings' => $totalEarnings, 
            ]
        ]);
    }

    public function edit(Technician $technician)
    {
        return Inertia::render('Technicians/Edit', [
            'technician' => $technician->load('user'),
            'availableSpecialties' => Technician::SPECIALTIES // Pasamos lista para editar
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
            'legal_name' => 'nullable|string',
            'rfc' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'clabe' => 'nullable|string',
            'status' => 'required|string',
            'internal_notes' => 'nullable|string',
            'rating_avg' => 'nullable|numeric|min:0|max:5',
            'tax_file' => 'nullable|file|mimes:pdf,jpg,png|max:5120', // CORRECCIÓN 1: Faltaba validar el archivo
        ]);

        DB::transaction(function () use ($validated, $request, $technician) {
            $technician->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null, 
            ]);

            if ($request->hasFile('photo')) {
                $optimizedPath = $this->imageOptimizer->optimize($request->file('photo'));
                $technician->user->updateProfilePhoto(new \Illuminate\Http\UploadedFile(
                    $optimizedPath,
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
                'legal_name' => $validated['legal_name'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account' => $validated['bank_account'] ?? null,
                'clabe' => $validated['clabe'] ?? null,
                'status' => $validated['status'] ?? $technician->status,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'rating_avg' => $validated['rating_avg'] ?? $technician->rating_avg,
            ]);

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

    public function updateStatus(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Activo,Inactivo,En revisión,Vetado'
        ]);

        $technician->update(['status' => $validated['status']]);

        return back()->with('success', 'Estatus del técnico actualizado.');
    }

    public function updateRating(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'rating' => 'required|numeric|min:0|max:5'
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
                'status' => 'Activo', 
                'rating_avg' => 0,
                'coverage_radius_km' => 10,
            ]);

            $user->load('technician'); 
        });

        return response()->json([
            'user' => $user,
            'message' => 'Técnico registrado rápidamente.'
        ], 201);
    }

    public function deleteMedia(Technician $technician, $mediaId)
    {
        $media = $technician->media()->findOrFail($mediaId);
        $media->delete();

        return back()->with('success', 'Documento eliminado.');
    }

    public function destroy(Technician $technician)
    {
        $technician->user->delete();
        return redirect()->route('technicians.index')->with('success', 'Técnico y usuario eliminados.');
    }
}