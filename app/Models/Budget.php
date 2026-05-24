<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Budget extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'ticket_id',
        'status',
        'description',
        'currency',
        'exchange_rate',
        'user_id',
        'invoice_date',
        'invoice_number',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'invoice_date'  => 'date',
    ];

    protected $appends = ['total_cost', 'total_paid', 'balance_due', 'total_catalog_cost'];

    // Relaciones
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Customer::class,
            Ticket::class,
            'id',            // tickets.id = budgets.ticket_id
            'id',            // customers.id = tickets.customer_id
            'ticket_id',     // budgets.ticket_id
            'customer_id'    // tickets.customer_id
        );
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(BudgetConcept::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BudgetPayment::class);
    }

    // --- NUEVA RELACIÓN ---
    public function technicianPayments(): HasMany
    {
        return $this->hasMany(TechnicianPayment::class);
    }

    public function catalogs(): HasMany
    {
        return $this->hasMany(BudgetCatalog::class);
    }
 
    public function latestCatalog(): HasOne
    {
        return $this->hasOne(BudgetCatalog::class)->latestOfMany('version');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invoice_document')->singleFile();
        $this->addMediaCollection('survey_images');
    }

    // Atributos Calculados
    public function getTotalCatalogCostAttribute(): float
    {
        $catalog = $this->relationLoaded('latestCatalog')
            ? $this->latestCatalog
            : $this->latestCatalog()->first();

        return $catalog ? (float) $catalog->total : 0;
    }

    public function getTotalCostAttribute(): float
    {
        $catalogTotal = $this->getTotalCatalogCostAttribute();

        return $catalogTotal > 0
            ? $catalogTotal
            : (float) $this->concepts->sum('amount');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return $this->total_cost - $this->total_paid;
    }

    // Filtros
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->whereHas('ticket', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    });
            });
        })->when($filters['status'] ?? null, function ($query, $status) {
            if ($status !== 'all') {
                $query->whereHas('ticket', function ($q) use ($status) {
                    $q->where('status', $status);
                });
            }
        })->when($filters['user_id'] ?? null, function ($query, $userId) {
            if ($userId !== 'all' && !empty($userId)) {
                if (is_array($userId)) {
                    $query->whereIn('user_id', $userId);
                } else {
                    $query->where('user_id', $userId);
                }
            }
        })->when($filters['branch'] ?? null, function ($query, $branch) {
            $query->whereHas('ticket.branch', function ($q) use ($branch) {
                $q->where('branch_name', 'like', '%' . $branch . '%')
                    ->orWhere('region', 'like', '%' . $branch . '%')
                    ->orWhere('unit', 'like', '%' . $branch . '%')
                    ->orWhere('country', 'like', '%' . $branch . '%');
            });
        });
    }
}
