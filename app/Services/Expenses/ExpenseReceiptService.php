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
     * Attach a receipt to the expense. Any previous receipt is replaced.
     * Images are optimized (resized + compressed) before being stored.
     */
    public function attach(Expense $expense, UploadedFile $file): void
    {
        $expense->clearMediaCollection('receipt');

        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $optimizedPath = $this->imageOptimizer->optimize($file);

            $expense->addMedia($optimizedPath)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('receipt');

            return;
        }

        $expense->addMedia($file)->toMediaCollection('receipt');
    }

    public function remove(Expense $expense): void
    {
        $expense->clearMediaCollection('receipt');
    }
}
