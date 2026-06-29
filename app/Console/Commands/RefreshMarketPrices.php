<?php

namespace App\Console\Commands;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Services\Keepa\KeepaClient;
use Illuminate\Console\Command;

/**
 * Records a Keepa market snapshot (lowest new/used price + sales rank) for every
 * book a seller currently holds in stock, building price history for monitoring.
 */
class RefreshMarketPrices extends Command
{
    protected $signature = 'keepa:refresh-prices {--sleep=300 : ms to wait between Keepa calls}';

    protected $description = 'Snapshot Amazon market prices (via Keepa) for in-stock books.';

    public function handle(KeepaClient $keepa): int
    {
        if (! $keepa->isConfigured()) {
            $this->warn('Keepa is not configured (set KEEPA_API_KEY); skipping.');

            return self::SUCCESS;
        }

        // Distinct books with in-stock inventory (no auth in console = all sellers).
        $products = Product::whereHas('inventoryItems', fn ($q) => $q->where('quantity', '>', 0))->get();
        $sleepMs = (int) $this->option('sleep');
        $recorded = 0;

        foreach ($products as $product) {
            $snapshot = $keepa->snapshot($product->isbn13);

            if ($snapshot && ($snapshot->newPrice || $snapshot->usedPrice)) {
                PriceObservation::create([
                    'product_id' => $product->id,
                    'source' => 'keepa',
                    'asin' => $snapshot->asin,
                    'new_price' => $snapshot->newPrice?->amount,
                    'used_price' => $snapshot->usedPrice?->amount,
                    'sales_rank' => $snapshot->salesRank,
                    'currency' => $snapshot->usedPrice?->currency ?? $snapshot->newPrice?->currency ?? 'GBP',
                    'observed_at' => now(),
                ]);
                $recorded++;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->info("Recorded {$recorded} market snapshot(s) across {$products->count()} book(s).");

        return self::SUCCESS;
    }
}
