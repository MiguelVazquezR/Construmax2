<?php

namespace App\Services\Payroll;

use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

class ImageAttachmentService
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}

    /**
     * Attach an uploaded file to a media collection, optimizing images.
     */
    public function attach(HasMedia $model, UploadedFile $file, string $collection): void
    {
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $model->addMedia($this->imageOptimizer->optimize($file))
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection($collection);

            return;
        }

        $model->addMedia($file)
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection($collection);
    }
}
