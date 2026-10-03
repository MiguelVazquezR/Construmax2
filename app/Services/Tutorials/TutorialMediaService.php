<?php

namespace App\Services\Tutorials;

use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TutorialMediaService
{
    public const VIDEO_DIRECTORY = 'tutorials/videos';

    public const THUMBNAIL_DIRECTORY = 'tutorials/thumbnails';

    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}

    /**
     * Store an uploaded video on the public disk and return its serving path.
     */
    public function storeVideo(UploadedFile $file): string
    {
        $path = $file->store(self::VIDEO_DIRECTORY, 'public');

        return $this->publicPath($path);
    }

    /**
     * Store an optimized thumbnail on the public disk and return its serving path.
     */
    public function storeThumbnail(UploadedFile $file): string
    {
        $optimizedPath = $this->imageOptimizer->optimize($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = self::THUMBNAIL_DIRECTORY.'/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, file_get_contents($optimizedPath));
        @unlink($optimizedPath);

        return $this->publicPath($path);
    }

    /**
     * Delete a media file from the public disk. Legacy paths (like /videos/*)
     * belong to the deployment and are never removed.
     */
    public function delete(?string $path): void
    {
        if (blank($path) || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(Str::after($path, '/storage/'));
    }

    private function publicPath(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }
}
