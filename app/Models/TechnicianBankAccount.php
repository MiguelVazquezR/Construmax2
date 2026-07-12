<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TechnicianBankAccount extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'technician_id',
        'account_number',
        'card_number',
        'clabe',
        'branch_number',
        'is_favorite',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bank_qr')->singleFile();
    }
}
