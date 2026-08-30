<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Models\ExportBatch;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds Amazon Inventory Loader CSV exports for the "Ready to list" items and
 * records each export as a re-downloadable batch that can later be marked listed.
 */
class AmazonExportService
{
    private const COLUMNS = [
        'sku', 'product-id', 'product-id-type', 'price', 'item-condition',
        'quantity', 'add-delete', 'item-note', 'fulfillment-center-id',
    ];

    /** Items currently ready to be exported (graded, priced, in stock). */
    public function readyItems(User $user): Collection
    {
        return $user->inventoryItems()
            ->with('product')
            ->where('status', InventoryStatus::ReadyToList)
            ->where('quantity', '>', 0)
            ->latest()
            ->get();
    }

    /**
     * Create an export batch from the user's ready items, or null if none.
     */
    public function createBatch(User $user): ?ExportBatch
    {
        $items = $this->readyItems($user);
        if ($items->isEmpty()) {
            return null;
        }

        return $user->exportBatches()->create([
            'filename' => 'amazon-inventory-'.now()->format('Y-m-d-His').'.csv',
            'item_ids' => $items->pluck('id')->all(),
            'item_count' => $items->count(),
            'csv' => $this->buildCsv($items),
        ]);
    }

    /**
     * Mark a batch's items as Listed. Returns the number updated.
     */
    public function markListed(ExportBatch $batch): int
    {
        $updated = InventoryItem::query()
            ->whereIn('id', $batch->item_ids)
            ->where('status', InventoryStatus::ReadyToList)
            ->update(['status' => InventoryStatus::Listed]);

        $batch->update(['marked_listed_at' => now()]);

        return $updated;
    }

    /**
     * The Amazon Inventory Loader CSV for a set of items.
     */
    public function buildCsv(Collection $items): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::COLUMNS);

        foreach ($items as $item) {
            fputcsv($out, [
                $item->sku,
                // ISBN-10 matches Amazon's ISBN type most reliably; fall back to ISBN-13.
                $item->product->isbn10 ?: $item->product->isbn13,
                2, // product-id-type: 2 = ISBN
                $item->list_price ?? $item->suggested_price,
                $item->condition->amazonInventoryLoaderCode(),
                $item->quantity,
                'a', // add
                $item->condition_note,
                '', // fulfillment-center-id blank = merchant-fulfilled
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
