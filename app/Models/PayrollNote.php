<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Free comment of the payroll team about a collaborator inside a period.
 * It is informational: it does not change the pre-payroll numbers.
 */
class PayrollNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'body',
        'created_by',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForPeriod(Builder $query, int $periodId): Builder
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
