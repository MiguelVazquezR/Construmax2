<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    public const SOURCE_LFT = 'lft';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'date',
        'name',
        'year',
        'source',
        'is_mandatory',
        'apply_extra_pay',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'is_mandatory' => 'boolean',
        'apply_extra_pay' => 'boolean',
        'year' => 'integer',
    ];

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
