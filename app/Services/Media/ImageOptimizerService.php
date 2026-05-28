<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ImageOptimizerService
{
    /**
     * @param int $maxWidth  Maximum width in pixels before resizing down
     * @param int $maxHeight Maximum height in pixels before resizing down
     * @param int $quality   JPEG/WebP quality (0–100). PNG ignores this and uses compression level 6 instead.
     */
    public function __construct(
        private readonly int $maxWidth = 1920,
        private readonly int $maxHeight = 1920,
        private readonly int $quality = 75,
    ) {}

    /**
     * Optimize an uploaded image: resize if too large and compress.
     *
     * @param  UploadedFile $file The uploaded image file.
     * @return string              Absolute path to the optimized temporary file.
     *
     * @throws RuntimeException    If the image type is unsupported or GD operations fail.
     */
    public function optimize(UploadedFile $file): string
    {
        $sourcePath = $file->getRealPath();

        [$width, $height, $type] = getimagesize($sourcePath);

        if ($width === 0 || $height === 0) {
            throw new RuntimeException('Unable to read image dimensions.');
        }

        $sourceGd = $this->createGdResource($sourcePath, $type);

        // --- Resize (maintain aspect ratio) ---
        $newWidth  = $width;
        $newHeight = $height;

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth  = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);
        }

        $resizedGd = $this->resize($sourceGd, (int) $width, (int) $height, $newWidth, $newHeight);
        imagedestroy($sourceGd);

        // --- Save optimized version ---
        $tempPath = tempnam(sys_get_temp_dir(), 'opt_') . $this->extensionFromType($type);
        $this->saveGdResource($resizedGd, $tempPath, $type);
        imagedestroy($resizedGd);

        return $tempPath;
    }

    // ─── Private helpers ────────────────────────────────────────────

    private function createGdResource(string $path, int $type): \GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_GIF  => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default        => throw new RuntimeException("Unsupported image type: {$type}"),
        };
    }

    private function resize(\GdImage $source, int $srcW, int $srcH, int $dstW, int $dstH): \GdImage
    {
        $dst = imagecreatetruecolor($dstW, $dstH);

        // Preserve transparency for PNG / GIF / WebP
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled(
            $dst, $source,
            0, 0,          // dst x, y
            0, 0,          // src x, y
            $dstW, $dstH,
            $srcW, $srcH,
        );

        return $dst;
    }

    private function saveGdResource(\GdImage $image, string $path, int $type): void
    {
        $success = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $this->quality),
            IMAGETYPE_PNG  => imagepng($image, $path, 6),           // Compression level 6 (0–9)
            IMAGETYPE_GIF  => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, $this->quality),
            default        => false,
        };

        if (! $success) {
            throw new RuntimeException('Failed to save optimized image.');
        }
    }

    private function extensionFromType(int $type): string
    {
        return match ($type) {
            IMAGETYPE_JPEG => '.jpg',
            IMAGETYPE_PNG  => '.png',
            IMAGETYPE_GIF  => '.gif',
            IMAGETYPE_WEBP => '.webp',
            default        => '.jpg',
        };
    }
}
