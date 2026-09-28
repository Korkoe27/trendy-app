<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStockMovements extends Command
{
    protected $signature = 'stock-movements:backfill {--dry-run : Preview counts without writing any rows}';

    protected $description = 'One-time, idempotent backfill of the stock_movements ledger from existing stocks snapshots';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $productIds = Stock::select('product_id')->distinct()->pluck('product_id');

        $this->info('Backfilling movements for '.$productIds->count().' product(s)'.($dryRun ? ' (dry run)' : ''));

        $totalInserted = 0;
        $totalSkipped = 0;

        $bar = $this->output->createProgressBar($productIds->count());
        $bar->start();

        foreach ($productIds as $productId) {
            $stocks = Stock::where('product_id', $productId)
                ->orderBy('created_at', 'asc')
                ->get();

            $previousTotal = 0;
            $rows = [];

            foreach ($stocks as $stock) {
                $opening = $previousTotal;
                $closing = (float) $stock->total_units;
                $delta = $closing - $opening;
                $previousTotal = $closing;

                $alreadyImported = StockMovement::where('reference_type', 'stock')
                    ->where('reference_id', $stock->id)
                    ->exists();

                if ($alreadyImported) {
                    $totalSkipped++;

                    continue;
                }

                $metadata = is_string($stock->metadata) ? json_decode($stock->metadata, true) : ($stock->metadata ?? []);
                $metadata = is_array($metadata) ? $metadata : [];
                $source = $metadata['source'] ?? null;

                $type = match ($source) {
                    'daily_inventory' => 'inventory',
                    'inventory_edit' => 'edit',
                    default => 'purchase',
                };

                $rows[] = [
                    'product_id' => $productId,
                    'type' => $type,
                    'quantity_delta' => $delta,
                    'reference_type' => 'stock',
                    'reference_id' => $stock->id,
                    'occurred_at' => $stock->occurred_at ?? $stock->created_at,
                    'metadata' => json_encode([
                        'legacy_import' => true,
                        'opening' => $opening,
                        'closing' => $closing,
                        'original_metadata' => $metadata,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($rows)) {
                $totalInserted += count($rows);

                if (! $dryRun) {
                    DB::table('stock_movements')->insert($rows);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Movements inserted: {$totalInserted}");
        $this->info("Already imported (skipped): {$totalSkipped}");

        if ($dryRun) {
            $this->warn('Dry run — no rows were written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
