<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Budget extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'service_type',
        'status',
        'description',
        'duration',
        'priority',
        'user_id',
        'customer_id',
        'customer_contact_id',
        'branch',
    ];

    // Relaciones
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Conceptos del presupuesto
    public function concepts(): HasMany
    {
        return $this->hasMany(BudgetConcept::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BudgetPayment::class);
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
            $query->where('name', 'like', '%'.$search.'%')
                  ->orWhereHas('customer', function($q) use ($search){
                      $q->where('name', 'like', '%'.$search.'%');
                  });
        })->when($filters['status'] ?? null, function ($query, $status) {
            if($status !== 'all') {
                $query->where('status', $status);
            }
        });
    }
}