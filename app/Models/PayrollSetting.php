<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSetting extends Model
{
    use HasFactory;

    // --- Payroll periods ---

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_BIWEEKLY = 'biweekly';

    public const PERIOD_SEMIMONTHLY = 'semimonthly';

    public const PERIOD_TYPES = [
        self::PERIOD_WEEKLY => 'Semanal',
        self::PERIOD_BIWEEKLY => 'Catorcenal',
        self::PERIOD_SEMIMONTHLY => 'Quincenal',
    ];

    // --- Late arrivals ---

    public const LATE_TRACK_ONLY = 'track_only';

    public const LATE_DEDUCT_MINUTES = 'deduct_minutes';

    public const LATE_DISCOUNT_MODES = [
        self::LATE_TRACK_ONLY => 'Solo registrar retardos',
        self::LATE_DEDUCT_MINUTES => 'Descontar minutos de retardo',
    ];

    // --- Vacation premium notices ---

    public const PREMIUM_NOTICE_ONCE = 'once';

    public const PREMIUM_NOTICE_DAILY = 'daily';

    public const PREMIUM_NOTICE_MODES = [
        self::PREMIUM_NOTICE_ONCE => 'Un solo aviso al inicio del periodo',
        self::PREMIUM_NOTICE_DAILY => 'Aviso cada día del periodo',
    ];

    protected $fillable = [
        'face_recognition_enabled',
        'face_match_threshold',
        'kiosk_pin_fallback_enabled',
        'rekognition_collection_id',
        'period_type',
        'period_anchor_date',
        'late_tolerance_minutes',
        'late_discount_mode',
        'overtime_double_multiplier',
        'overtime_triple_multiplier',
        'overtime_weekly_threshold_hours',
        'holiday_worked_extra_multiplier',
        'vacation_min_days_to_request',
        'vacation_carryover_months',
        'vacation_premium_notice_enabled',
        'vacation_premium_notice_mode',
        'incapacity_paid',
        'incapacity_pay_percentage',
        'default_daily_hours',
        'payroll_expense_category_id',
        'attendance_capture_retention_months',
        'remote_geolocation_required',
        'updated_by',
    ];

    protected $casts = [
        'face_recognition_enabled' => 'boolean',
        'face_match_threshold' => 'integer',
        'kiosk_pin_fallback_enabled' => 'boolean',
        'period_anchor_date' => 'date',
        'late_tolerance_minutes' => 'integer',
        'late_discount_mode' => 'string',
        'overtime_double_multiplier' => 'decimal:2',
        'overtime_triple_multiplier' => 'decimal:2',
        'overtime_weekly_threshold_hours' => 'decimal:2',
        'holiday_worked_extra_multiplier' => 'decimal:2',
        'vacation_min_days_to_request' => 'decimal:2',
        'vacation_carryover_months' => 'integer',
        'vacation_premium_notice_enabled' => 'boolean',
        'vacation_premium_notice_mode' => 'string',
        'incapacity_paid' => 'boolean',
        'incapacity_pay_percentage' => 'integer',
        'default_daily_hours' => 'decimal:2',
        'attendance_capture_retention_months' => 'integer',
        'remote_geolocation_required' => 'boolean',
    ];

    /**
     * The singleton settings row, created with column defaults on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'payroll_expense_category_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
