<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Technician extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia; // Para Constancia Fiscal, INE, Comprobantes

    // --- NIVELES / CATEGORÍAS ---
    public const LEVELS = ['Encargado', 'Auxiliar/Ayudante'];

    // --- LISTA MAESTRA DE ESPECIALIDADES ---
    public const SPECIALTIES = [
        'Electricidad baja tensión',
        'Electricidad alta tensión',
        'Plomería / Fontanería',
        'Aire acondicionado (HVAC)',
        'Tablaroca y acabados',
        'Pintura general',
        'Impermeabilización',
        'Albañilería',
        'Herrería y soldadura',
        'Vidrio y aluminio',
        'Redes y voz/datos',
        'Cerrajería',
        'Limpieza industrial',
        'Carpintería',
        'Pisos y azulejos',
        'Jardinería',
        'Fumigación y plagas',
        'Instalación de cámaras (CCTV)',
        'Domótica',
        'Mantenimiento de elevadores'
    ];

    protected $fillable = [
        'user_id',
        'phone',
        'secondary_phone',
        'is_internal',
        'state',
        'city',
        'colony',
        'zip_code',
        'coverage_radius_km',
        'specialties',
        'level',
        'legal_name',
        'rfc',
        'bank_name',
        'bank_account',
        'clabe',
        'status',
        'rating_avg',
        'internal_notes',
    ];

    protected $casts = [
        'specialties' => 'array',
        'is_internal' => 'boolean',
        'rating_avg' => 'float',
    ];

    // --- RELACIONES ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Historial Operativo: Tickets donde este técnico participa (vía JSON technicians o tasks)
    // No se puede usar hasMany estándar porque technicians es JSON; usar scope o query directa.
    public function scopeWhereInvolved(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereJsonContains('technicians', (string) $userId)
              ->orWhereJsonContains('technicians', (int) $userId)
              ->orWhereHas('tasks', function ($t) use ($userId) {
                  $t->where('user_id', $userId);
              });
        });
    }

    // --- SCOPES Y FILTROS ---

    public function scopeFilter($query, array $filters)
    {
        // Búsqueda general
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                // Buscamos en datos del técnico
                $q->where('legal_name', 'like', '%'.$search.'%')
                  ->orWhere('rfc', 'like', '%'.$search.'%')
                  ->orWhere('city', 'like', '%'.$search.'%')
                  // O buscamos en el Usuario relacionado (Nombre, Email)
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                  });
            });
        });

        // Filtro por Especialidad (Soporta Array Múltiple y Unicode Escapado)
        $query->when($filters['specialty'] ?? null, function ($query, $specialty) {
            // Normalizamos a array para manejar selección simple o múltiple igual
            $specialties = is_array($specialty) ? $specialty : [$specialty];

            if (count($specialties) > 0) {
                $query->where(function ($q) use ($specialties) {
                    foreach ($specialties as $s) {
                        // 1. Búsqueda nativa JSON (Funciona si la BD soporta JSON nativo bien configurado)
                        $q->orWhereJsonContains('specialties', $s);

                        // 2. Búsqueda de compatibilidad para Unicode Escapado (Ej: "Instalaci\u00f3n")
                        // Esto soluciona el problema donde 'json_encode' guardó acentos como códigos unicode
                        // y whereJsonContains no los matchea en columnas TEXT o versiones antiguas de MySQL.
                        $unicodeEscaped = trim(json_encode($s), '"'); 
                        
                        // Solo agregamos la condición LIKE si hay caracteres especiales que se escaparon
                        if ($unicodeEscaped !== $s) {
                            // Usamos LIKE porque whereJsonContains espera el valor decodificado
                            // Escapamos las barras invertidas para el query SQL (\\u00f3)
                            $sqlEscaped = str_replace('\\', '\\\\', $unicodeEscaped);
                            $q->orWhere('specialties', 'like', '%' . $sqlEscaped . '%');
                        }
                    }
                });
            }
        });

        // Filtro por Estado/Ubicación
        $query->when($filters['state'] ?? null, function ($query, $state) {
            $query->where('state', $state);
        });
    }

    // --- MÉTRICAS (KPIs) ---

    // Calcula y actualiza el promedio de calificación
    public function updateRating()
    {
        // Lógica futura para actualizar rating
    }
}