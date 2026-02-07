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
     * Automatización de Estatus
     * Se llama desde el controlador de tareas al modificar avances
     */
    public function updateStatusBasedOnTasks()
    {
        // Recargar tareas para tener el conteo fresco
        $this->load('tasks');
        
        $total = $this->tasks->count();
        $completed = $this->tasks->where('status', 'Completada')->count();
        $currentStatus = $this->status;
        $newStatus = $currentStatus;

        if ($total > 0) {
            if ($completed === $total) {
                // Si TODAS están completas -> Completado
                $newStatus = 'Completado';
            } elseif ($completed > 0 && $currentStatus === 'Programado') {
                // Si hay avance y estaba en "Programado" -> En proceso
                $newStatus = 'En proceso';
            } elseif ($completed === 0 && $currentStatus === 'Completado') {
                // Si se desmarcaron todas -> Regresa a Programado (o En proceso si hubiera logica intermedia)
                $newStatus = 'Programado';
            }
        } elseif ($total === 0 && $currentStatus === 'Completado') {
            // Si se borraron todas las tareas -> Programado
            $newStatus = 'Programado';
        }

        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}