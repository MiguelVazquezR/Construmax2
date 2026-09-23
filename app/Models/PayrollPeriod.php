<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    public const TYPE_WEEKLY = 'weekly';

    public const TYPE_BIWEEKLY = 'biweekly';

    public const TYPE_SEMIMONTHLY = 'semimonthly';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'type',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'reopened_at',
        'total_gross',
        'total_deductions',
        'total_net',
        'expense_id',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PayrollNote::class, 'payroll_period_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * True when the period was closed before its last date (an early manual
     * close): the remaining days stay without an open period until the pocket
     * is reopened or the next Monday arrives.
     */
    public function closedEarly(): bool
    {
        return $this->closed_at !== null
            && $this->closed_at->toDateString() < $this->end_date->toDateString();
    }

    public function label(): string
    {
        return $this->start_date->format('d/m/Y').' — '.$this->end_date->format('d/m/Y');
    }
}
