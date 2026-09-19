<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_number',
        'hire_date',
        'termination_date',
        'daily_salary',
        'daily_hours',
        'is_payroll_subject',
        'is_attendance_subject',
        'can_remote_attendance',
        'kiosk_pin',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'daily_salary' => 'decimal:2',
        'daily_hours' => 'decimal:2',
        'is_payroll_subject' => 'boolean',
        'is_attendance_subject' => 'boolean',
        'can_remote_attendance' => 'boolean',
        'kiosk_pin' => 'hashed',
    ];

    protected $hidden = [
        'kiosk_pin',
    ];

    protected $appends = [
        'has_kiosk_pin',
    ];

    /**
     * Whether the profile has a kiosk fallback pin configured (the pin itself is never exposed).
     */
    public function getHasKioskPinAttribute(): bool
    {
        return ! empty($this->kiosk_pin);
    }

    protected static function booted(): void
    {
        static::creating(function (PayrollProfile $profile) {
            if (empty($profile->employee_number)) {
                $profile->employee_number = static::nextEmployeeNumber();
            }
        });
    }

    /**
     * Sequential employee number (EMP-0001, EMP-0002, ...).
     */
    public static function nextEmployeeNumber(): string
    {
        $next = (int) static::query()->max('id') + 1;

        return 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
