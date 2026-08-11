<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetConcept extends Model
{
    use HasFactory;

    protected $fillable = ['budget_id', 'concept', 'amount', 'paid_to_technician', 'payment_date'];

    protected $casts = [
        'paid_to_technician' => 'boolean',
        'payment_date' => 'date',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}