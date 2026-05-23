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
        'description',
        'unit',
        'quantity',
        'unit_price',
        'total',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(BudgetCatalog::class, 'budget_catalog_id');
    }
}