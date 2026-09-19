<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AttendanceLog extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    // --- Punch types ---

    public const TYPE_CHECK_IN = 'check_in';

    public const TYPE_LUNCH_START = 'lunch_start';

    public const TYPE_LUNCH_END = 'lunch_end';

    public const TYPE_BREAK_START = 'break_start';

    public const TYPE_BREAK_END = 'break_end';

    public const TYPE_CHECK_OUT = 'check_out';

    public const TYPES = [
        self::TYPE_CHECK_IN => 'Entrada',
        self::TYPE_LUNCH_START => 'Inicio de comida',
        self::TYPE_LUNCH_END => 'Fin de comida',
        self::TYPE_BREAK_START => 'Permiso / salida',
        self::TYPE_BREAK_END => 'Regreso de permiso',
        self::TYPE_CHECK_OUT => 'Salida',
    ];

    // --- Sources ---

    public const SOURCE_KIOSK = 'kiosk';

    public const SOURCE_REMOTE = 'remote';

    public const SOURCE_MANUAL = 'manual';

    // --- Identification methods ---

    public const IDENTIFIER_FACE = 'face';

    public const IDENTIFIER_PIN = 'pin';

    public const IDENTIFIER_MANUAL = 'manual';

    protected $fillable = [
        'user_id',
        'attendance_device_id',
        'type',
        'punched_at',
        'source',
        'identifier_method',
        'face_similarity',
        'latitude',
        'longitude',
        'location_accuracy',
        'ip',
        'user_agent',
        'edited_by',
        'edited_at',
        'edit_reason',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'edited_at' => 'datetime',
        'face_similarity' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_accuracy' => 'float',
    ];

    protected $appends = [
        'type_label',
        'capture_url',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('capture')->singleFile();
    }

    /**
     * Human readable label of the punch source.
     */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_KIOSK => 'Kiosco',
            self::SOURCE_REMOTE => 'Remoto',
            self::SOURCE_MANUAL => 'Manual',
            default => (string) $this->source,
        };
    }

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    // --- Accessors ---

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getCaptureUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('capture') ?: null;
    }

    // --- Scopes ---

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('punched_at', $date);
    }

    /**
     * Suggest the next punch type based on the punches already registered today.
     *
     * @param  Collection<int, self>  $logs  Ordered chronologically.
     */
    public static function nextSuggestedType(Collection $logs): string
    {
        $completed = $logs->pluck('type')->all();

        if (! in_array(self::TYPE_CHECK_IN, $completed, true)) {
            return self::TYPE_CHECK_IN;
        }

        $hasLunchStart = in_array(self::TYPE_LUNCH_START, $completed, true);
        $hasLunchEnd = in_array(self::TYPE_LUNCH_END, $completed, true);

        if ($hasLunchStart && ! $hasLunchEnd) {
            return self::TYPE_LUNCH_END;
        }

        $hasBreakStart = in_array(self::TYPE_BREAK_START, $completed, true);
        $hasBreakEnd = in_array(self::TYPE_BREAK_END, $completed, true);

        if ($hasBreakStart && ! $hasBreakEnd) {
            return self::TYPE_BREAK_END;
        }

        if (! $hasLunchStart) {
            return self::TYPE_LUNCH_START;
        }

        return self::TYPE_CHECK_OUT;
    }
}
