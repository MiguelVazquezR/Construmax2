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
        'budget_id',
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

    // Relaciones
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    // Accessors
    public function getCustomerNameAttribute()
    {
        return $this->budget->customer->name ?? 'N/A';
    }

    public function getServiceTypeAttribute()
    {
        return $this->budget->service_type ?? 'N/A';
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
     * * Esto cubre:
     * 1. Reabrir ticket: Estaba 'Completado', agregas tarea nueva -> Pasa a 'En proceso'.
     * 2. Iniciar ticket: Estaba 'Programado', agregas tarea -> Pasa a 'En proceso'.
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
            // Sin tareas definidas, asumimos estado base
            $newStatus = 'Programado';
        } elseif ($completed === $total) {
            // Tareas existen y todas completadas
            $newStatus = 'Completado';
        } else {
            // Tareas existen pero no todas están completas ($completed < $total)
            // Esto implica que hay trabajo pendiente, por lo tanto "En proceso"
            $newStatus = 'En proceso';
        }

        // Solo actualizamos si hubo cambio para no disparar eventos innecesarios
        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}