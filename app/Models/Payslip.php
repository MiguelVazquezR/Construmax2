<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'employee_number',
        'department',
        'position',
        'hire_date',
        'daily_salary',
        'daily_hours',
        'days_worked',
        'days_paid',
        'unpaid_days',
        'late_minutes',
        'late_discount',
        'overtime_double_minutes',
        'overtime_triple_minutes',
        'overtime_amount',
        'holiday_days',
        'holiday_amount',
        'vacation_days',
        'incapacity_days',
        'incapacity_amount',
        'adjustments_earnings',
        'adjustments_deductions',
        'total_gross',
        'total_deductions',
        'total_net',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'daily_salary' => 'decimal:2',
        'daily_hours' => 'decimal:2',
        'days_worked' => 'decimal:2',
        'days_paid' => 'decimal:2',
        'unpaid_days' => 'decimal:2',
        'late_discount' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'holiday_days' => 'decimal:2',
        'holiday_amount' => 'decimal:2',
        'vacation_days' => 'decimal:2',
        'incapacity_days' => 'decimal:2',
        'incapacity_amount' => 'decimal:2',
        'adjustments_earnings' => 'decimal:2',
        'adjustments_deductions' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort_order');
    }

    public function days(): HasMany
    {
        return $this->hasMany(PayslipDay::class)->orderBy('date');
    }
}
