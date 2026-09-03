<?php

namespace App\Console\Commands;

use App\Models\BudgetCatalogItem;
use Illuminate\Console\Command;

class RepairCatalogTotals extends Command
{
    protected $signature = 'catalog:repair-totals {--dry-run : Show what would be repaired without updating}';

    protected $description = 'Recalculate and fix budget catalog item totals that were saved incorrectly (hours * rate for labor, quantity * unit_price for materials).';

    public function handle(): int
    {
        $items = BudgetCatalogItem::all();

        $fixed = 0;
        $alreadyCorrect = 0;

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            $expectedTotal = $item->type === 'labor'
                ? round((float) ($item->hours ?? 0) * (float) ($item->rate ?? 0), 2)
                : round((float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0), 2);

            $currentTotal = round((float) $item->total, 2);

            if (abs($currentTotal - $expectedTotal) < 0.005) {
                $alreadyCorrect++;
                $bar->advance();
                continue;
            }

            if ($this->option('dry-run')) {
                $this->newLine();
                $this->line("  [DRY RUN] Item #{$item->id} (type: {$item->type}): total {$currentTotal} -> {$expectedTotal}");
            } else {
                $item->update(['total' => $expectedTotal]);
            }

            $fixed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $mode = $this->option('dry-run') ? 'Items that would be fixed' : 'Total items fixed';
        $this->info("{$mode}: {$fixed}");
        $this->info("Already correct: {$alreadyCorrect}");

        return self::SUCCESS;
    }
}