<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    use HasFactory;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPES = [
        self::TYPE_EARNING => 'Percepción',
        self::TYPE_DEDUCTION => 'Deducción',
    ];

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'type',
        'concept',
        'amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
