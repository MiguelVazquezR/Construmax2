<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    protected $appends = ['total_cost', 'total_paid', 'balance_due'];

    // Relaciones
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invoice_document')->singleFile();
    }

    // Atributos Calculados
    public function getTotalCostAttribute()
    {
        return $this->concepts->sum('amount');
    }

    public function getTotalPaidAttribute()
    {
        return $this->payments->sum('amount');
    }

    public function getBalanceDueAttribute()
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
                $query->where('status', $status);
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
