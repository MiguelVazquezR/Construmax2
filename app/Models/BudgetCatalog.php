<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'version',
        'subtotal',
        'iva',
        'total',
        'non_installation_labor',
        'labor_utility',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetCatalogItem::class);
    }
}