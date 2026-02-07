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

    // Accessors rápidos para datos del presupuesto (evita cadenas largas en blade/vue)
    public function getCustomerNameAttribute()
    {
        return $this->budget->customer->name ?? 'N/A';
    }

    public function getServiceTypeAttribute()
    {
        return $this->budget->service_type ?? 'N/A';
    }
    
    // Progreso calculado basado en tareas completadas
    public function getProgressAttribute()
    {
        $total = $this->tasks->count();
        if ($total === 0) return 0;
        
        $completed = $this->tasks->where('status', 'Completada')->count();
        return round(($completed / $total) * 100);
    }
}