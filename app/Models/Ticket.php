<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'customer_id',
        'customer_contact_id',
        'customer_branch_id',
        'name',
        'service_type',
        'duration',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CustomerBranch::class, 'customer_branch_id');
    }

    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    // --- LÓGICA DE NEGOCIO ---

    public function generateTasksFromTemplate($templateId, array $technicianIds)
    {
        $template = TaskTemplate::with('items')->find($templateId);
        
        if (!$template || empty($technicianIds) || $template->items->isEmpty()) {
            return;
        }

        $tasksData = [];
        $now = now();

        foreach ($technicianIds as $techId) {
            foreach ($template->items as $item) {
                $tasksData[] = [
                    'ticket_id' => $this->id,
                    'user_id' => $techId,
                    'name' => $item->name,
                    'description' => $item->description,
                    'status' => 'Pendiente',
                    'start_date' => $this->scheduled_start ?? clone $now,
                    'due_date' => $this->scheduled_end ?? (clone $now)->addDays(1),
                    'created_at' => clone $now,
                    'updated_at' => clone $now,
                ];
            }
        }

        TicketTask::insert($tasksData);
        // No actualizamos el estatus aquí para garantizar que el ticket inicie y se mantenga como 'Borrador'
    }

    // --- ACCESSORS ---
    public function getCustomerNameAttribute()
    {
        return $this->customer->name ?? 'N/A';
    }
    
    public function getFolioAttribute()
    {
        $id = $this->id;
        $code = "UND";

        if ($this->relationLoaded('branch') && $this->branch) {
            $region = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->branch->region ?? 'X'), 0, 3));
            $country = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->branch->country ?? 'X'), 0, 2));
            $code = "{$region}-{$country}";
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
            if ($currentStatus !== 'Cancelado') {
                $newStatus = 'Borrador'; 
            }
        } elseif ($completed === $total) {
            $newStatus = 'Ejecutado';
        } else {
            // Si hay tareas pero ninguna está completada, y el ticket es nuevo (Borrador), lo mantenemos en Borrador.
            if ($completed === 0 && $currentStatus === 'Borrador') {
                $newStatus = 'Borrador';
            } else {
                $newStatus = 'Proceso de ejecución';
            }
        }

        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}