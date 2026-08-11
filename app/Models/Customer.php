<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Customer extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'type',
        'name',
        'business_name',
        'rfc',
        'payment_condition',
        'payment_method',
        'invoice_usage',
        'currency',
        'payment_days',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_days' => 'integer',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(CustomerBranch::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%");
            });
        })->when($filters['region'] ?? null, function ($query, $region) {
            $query->whereHas('branches', function ($q) use ($region) {
                $q->where('region', 'like', "%{$region}%")
                    ->orWhere('branch_name', 'like', "%{$region}%")
                    ->orWhere('unit', 'like', "%{$region}%");
            });
        })->when($filters['contact'] ?? null, function ($query, $contact) {
            $query->whereHas('contacts', function ($q) use ($contact) {
                $q->where('name', 'like', "%{$contact}%");
            });
        });
    }
}