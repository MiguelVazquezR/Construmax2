<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

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
    ];

    protected $hidden = [
        'kiosk_pin',
        'kiosk_pin_lookup',
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

    /**
     * The kiosk pin is stored as a bcrypt hash (never reversible); the extra
     * HMAC lookup allows the kiosk to find the collaborator from the typed pin
     * without scanning every bcrypt hash.
     */
    public function setKioskPinAttribute(?string $value): void
    {
        $pin = trim((string) $value);

        // An empty value keeps the current pin.
        if ($pin === '') {
            return;
        }

        $this->attributes['kiosk_pin'] = Hash::make($pin);
        $this->attributes['kiosk_pin_lookup'] = self::pinLookup($pin);
    }

    /**
     * Deterministic lookup hash of a kiosk pin, keyed with the app key.
     */
    public static function pinLookup(string $pin): string
    {
        return hash_hmac('sha256', trim($pin), (string) config('app.key'));
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

    /**
     * Whether the collaborator belongs to the payroll on the given date:
     * from the hire date up to (and including) the termination date.
     */
    public function isActiveOn(CarbonInterface|string $date): bool
    {
        $value = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        if ($this->hire_date && $this->hire_date->toDateString() > $value) {
            return false;
        }

        return $this->termination_date === null || $this->termination_date->toDateString() >= $value;
    }

    /**
     * Profiles that are part of the payroll on the given date. Collaborators
     * terminated before that date (or hired after it) are excluded.
     */
    public function scopePayrollSubjectOn(Builder $query, CarbonInterface|string $date): Builder
    {
        $value = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query
            ->where('is_payroll_subject', true)
            ->where(function (Builder $query) use ($value) {
                $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $value);
            })
            ->where(function (Builder $query) use ($value) {
                $query->whereNull('termination_date')->orWhereDate('termination_date', '>=', $value);
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
