<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_catalog_id',
        'type',
        'description',
        'unit',
        'technician',
        'hours',
        'rate',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'rate'  => 'decimal:2',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(BudgetCatalog::class, 'budget_catalog_id');
    }
}