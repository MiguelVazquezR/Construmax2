<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    /**
     * Budget concept cost type matched by each default category. Used by the
     * expenses filter so these categories also match their concept payments.
     */
    public const DEFAULT_COST_TYPES = [
        'Mano de obra' => 'labor',
        'Materiales' => 'material',
    ];

    protected $fillable = [
        'name',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // --- Relationships ---

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Cost type of budget concepts matched by this category (defaults only).
     */
    public function conceptCostType(): ?string
    {
        return $this->is_default
            ? (self::DEFAULT_COST_TYPES[$this->name] ?? null)
            : null;
    }
}
