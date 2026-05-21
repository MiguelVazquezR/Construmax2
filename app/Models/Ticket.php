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

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    // --- LOGICA DE NEGOCIO (SOLID) ---

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
        $this->updateStatusBasedOnTasks();
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

        if ($this->relationLoaded('contact') && $this->contact && is_array($this->contact->branches)) {
            $branchData = collect($this->contact->branches)->firstWhere('unit', $this->branch);
            
            if ($branchData) {
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
        
        $currentStatus = $this->status;
        $newStatus = $currentStatus;

        $newStatus = 'Borrador'; 

        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}