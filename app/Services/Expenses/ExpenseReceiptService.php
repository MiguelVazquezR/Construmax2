<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\UploadedFile;

class ExpenseReceiptService
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}

    /**
     * Attach one or more receipts to the expense. Images are optimized
     * (resized + compressed) before being stored.
     *
     * @param array<int, UploadedFile> $files
     */
    public function attachMany(Expense $expense, array $files): void
    {
        foreach ($files as $file) {
            $this->attach($expense, $file);
        }
    }

    public function attach(Expense $expense, UploadedFile $file): void
    {
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $optimizedPath = $this->imageOptimizer->optimize($file);

            $expense->addMedia($optimizedPath)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('receipt');

            return;
        }

        $expense->addMedia($file)->toMediaCollection('receipt');
    }

    /**
     * Delete the given receipt files from the expense.
     *
     * @param array<int, int|string> $mediaIds
     */
    public function removeMany(Expense $expense, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $expense->getMedia('receipt')
            ->whereIn('id', $mediaIds)
            ->each(fn ($media) => $media->delete());
    }
}
