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
        'technicians',
        'status',
        'priority',
        'scheduled_start',
        'scheduled_end',
        'instructions',
    ];

    protected $casts = [
        'scheduled_start' => 'date',
        'scheduled_end' => 'date',
        'technicians' => 'array', 
    ];

    // Agregamos 'folio' para que siempre viaje en las peticiones JSON
    protected $appends = ['progress', 'folio'];

    // --- RELACIONES ---

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
    
    // Folio dinámico inteligente (Ej. #34-JAL-ME)
    public function getFolioAttribute()
    {
        $id = $this->id;
        $code = "UND"; // Por si no hay datos (Undefined)

        // Verificamos si tenemos el contacto y sus sucursales
        if ($this->relationLoaded('contact') && $this->contact && is_array($this->contact->branches)) {
            // Buscamos la sucursal específica de este ticket
            $branchData = collect($this->contact->branches)->firstWhere('unit', $this->branch);
            
            if ($branchData) {
                // Tomamos las primeras 3 letras de la región y 2 del país, quitando caracteres especiales
                $region = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchData['region'] ?? 'X'), 0, 3));
                $country = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchData['country'] ?? 'X'), 0, 2));
                $code = "{$region}-{$country}";
            }
        }

        return "#{$id}-{$code}";
    }

    public function getProgressAttribute()
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        
        $total = $tasks->count();
        if ($total === 0) return 0;
        
        $completed = $tasks->where('status', 'Completada')->count();
        return round(($completed / $total) * 100);
    }

    public function updateStatusBasedOnTasks()
    {
        $this->load('tasks');
        
        $total = $this->tasks->count();
        $completed = $this->tasks->where('status', 'Completada')->count();
        $currentStatus = $this->status;
        $newStatus = $currentStatus;

        if ($total === 0) {
            $newStatus = 'Borrador'; // Cambiado el estatus por defecto
        } elseif ($completed === $total) {
            $newStatus = 'Ejecutado';
        } else {
            $newStatus = 'Proceso de ejecución';
        }

        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}