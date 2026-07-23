<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Actions\Notifications\DispatchNotificationAction;

class Ticket extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * Boot the model and register status change notifications.
     */
    protected static function booted(): void
    {
        static::updated(function (Ticket $ticket) {
            if ($ticket->wasChanged('status')) {
                $action = app(DispatchNotificationAction::class);

                match ($ticket->status) {
                    'Finalizado' => $action->ticketNeedsInvoice($ticket),
                    default      => null,
                };
            }
        });
    }

    protected $fillable = [
        'customer_id',
        'customer_contact_id',
        'customer_branch_id',
        'seller_id',
        'name',
        'service_type',
        'report_number',
        'duration',
        'technicians',
        'assistant_technicians',
        'status',
        'priority',
        'scheduled_start',
        'scheduled_end',
        'instructions',
        'important_note',
        'has_oc',
    ];

    protected $casts = [
        'scheduled_start' => 'date',
        'scheduled_end' => 'date',
        'technicians' => 'array',
        'assistant_technicians' => 'array',
        'has_oc' => 'boolean',
    ];

    protected $appends = ['progress', 'folio'];

    /**
     * Override Spatie's media() to always order by order_column.
     * Spatie v11's raw relationship lacks the orderBy, so eager-loaded
     * media comes unsorted.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(config('media-library.media_model', Media::class), 'model')
            ->orderBy('order_column');
    }

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

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    public function workAcceptanceReport(): HasOne
    {
        return $this->hasOne(WorkAcceptanceReport::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
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

        if ($this->branch) {
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
            // If tasks exist but none completed, and ticket was Borrador or Programado
            if ($completed === 0 && in_array($currentStatus, ['Borrador', 'Programado'])) {
                $newStatus = $currentStatus;
            } else {
                $newStatus = 'Proceso de ejecución';
            }
        }

        if ($newStatus !== $currentStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    // --- SCOPES ---

    /**
     * Find tickets where a technician participated (via JSON technicians,
     * assistant_technicians, or assigned tasks).
     *
     * @param int $userId The user_id of the technician (stored in tickets.technicians JSON).
     */
    public function scopeWhereInvolved(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereJsonContains('technicians', (string) $userId)
              ->orWhereJsonContains('technicians', (int) $userId)
              ->orWhereJsonContains('assistant_technicians', (string) $userId)
              ->orWhereJsonContains('assistant_technicians', (int) $userId)
              ->orWhereHas('tasks', function ($t) use ($userId) {
                  $t->where('user_id', $userId);
              });
        });
    }
}