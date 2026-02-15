<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'business_name',
        'rfc',
        'payment_condition',
        'payment_method',
        'invoice_usage',
        'currency',
        'payment_days', // Campo numérico
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_days' => 'integer',
    ];

    // Relación: Un cliente tiene muchos contactos
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    // Relación: Un cliente tiene muchos presupuestos
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
    
    // Scope para búsquedas
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                  ->orWhere('business_name', 'like', '%'.$search.'%')
                  ->orWhere('rfc', 'like', '%'.$search.'%');
            });
        });
    }
}