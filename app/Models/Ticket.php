<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'customer_id',
        'customer_contact_id',
        'branch',
        'name',
        'service_type',
        'duration',
        'user_id',
        'status',
        'priority',
        'scheduled_start',
        'scheduled_end',
        'instructions',
    ];

    protected $casts = [
        'scheduled_start' => 'date',
        'scheduled_end' => 'date',
    ];

    // IMPORTANTE: Esto asegura que 'progress' se envíe siempre en el JSON
    protected $appends = ['progress'];

    // --- RELACIONES NUEVAS Y ACTUALIZADAS ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    public function budgets(): HasMany
    {
        // Ahora un Ticket puede tener múltiples presupuestos asociados (cotizaciones, revisiones)
        return $this->hasMany(Budget::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    // --- ACCESSORS ---
    public function getCustomerNameAttribute()
    {
        return $this->customer->name ?? 'N/A';
    }
    
    // Progreso calculado
    public function getProgressAttribute()
    {
        // Usamos la relación cargada o la cargamos si no existe
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        
        $total = $tasks->count();
        if ($total === 0) return 0;
        
        $completed = $tasks->where('status', 'Completada')->count();
        return round(($completed / $total) * 100);
    }

    /**
     * Automatización de Estatus Inteligente
     * - Si no hay tareas -> Programado
     * - Si hay tareas y TODAS están completas -> Completado
     * - Si hay tareas y AL MENOS UNA está pendiente -> En proceso
     */
    public function updateStatusBasedOnTasks()
    {
        // Forzamos recarga para asegurar consistencia tras crear/borrar
        $this->load('tasks');
        
        $total = $this->tasks->count();
        $completed = $this->tasks->where('status', 'Completada')->count();
        $currentStatus = $this->status;
        $newStatus = $currentStatus;

        if ($total === 0) {
            $newStatus = 'Programado';
        } elseif ($completed === $total) {
            $newStatus = 'Completado';
        } else {
            $newStatus = 'En proceso';
        }

        // Solo actualizamos si hubo cambio para no disparar eventos innecesarios
        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}