<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BudgetConcept extends Model
{
    use HasFactory;

    protected $fillable = ['budget_id', 'concept', 'amount', 'type', 'paid_to_technician', 'payment_date'];

    protected $casts = [
        'paid_to_technician' => 'boolean',
        'payment_date' => 'date',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Payment registered for this concept from the expenses module.
     */
    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class, 'budget_concept_id');
    }
}