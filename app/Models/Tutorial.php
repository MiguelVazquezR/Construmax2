<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Tutorial extends Model
{
    protected $fillable = [
        'title',
        'description',
        'duration',
        'video_path',
        'thumbnail_path',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Legacy entries may point to /videos/* files that are not deployed, so
     * the gallery checks the physical files before exposing their URLs.
     */
    public function videoAvailable(): bool
    {
        return $this->fileExists($this->video_path);
    }

    public function thumbnailAvailable(): bool
    {
        return $this->fileExists($this->thumbnail_path);
    }

    private function fileExists(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        if (str_starts_with($path, '/storage/')) {
            return Storage::disk('public')->exists(Str::after($path, '/storage/'));
        }

        return file_exists(public_path(ltrim($path, '/')));
    }

    /**
     * Shape used by the tutorials gallery in the frontend.
     */
    public function toVideoPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'duration' => $this->duration,
            'video_url' => $this->video_path,
            'video_available' => $this->videoAvailable(),
            // Missing thumbnails are not exposed so the browser does not request them (no 404 noise).
            'thumbnail_url' => $this->thumbnailAvailable() ? $this->thumbnail_path : null,
        ];
    }
}
