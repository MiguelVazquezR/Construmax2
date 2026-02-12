<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Technician extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia; // Para Constancia Fiscal, INE, Comprobantes

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

    // Historial Operativo: Tickets donde este técnico es el responsable (vía User)
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'user_id', 'user_id');
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

        // Filtro por Especialidad (JSON)
        $query->when($filters['specialty'] ?? null, function ($query, $specialty) {
            // Sintaxis para buscar dentro de array JSON en MySQL
            $query->whereJsonContains('specialties', $specialty);
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
        // Aquí asumiríamos que los Tickets tienen un campo 'rating' (habría que agregarlo a Tickets)
        // Por ahora lo dejamos preparado
        /*
        $avg = $this->tickets()->whereNotNull('rating')->avg('rating');
        $this->update(['rating_avg' => $avg ?? 5.0]);
        */
    }
}