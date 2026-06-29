<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Jobs\PublishListingJob;
use App\Models\InventoryItem;
use App\Models\Listing;
use App\Models\MarketplaceAccount;

/**
 * Orchestrates publishing an inventory item to a marketplace. The request only
 * creates/refreshes the Listing record and queues the work; all channel I/O
 * happens in PublishListingJob so the web request returns immediately.
 */
class ListingService
{
    public function queuePublish(InventoryItem $item, MarketplaceAccount $account): Listing
    {
        $listing = Listing::updateOrCreate(
            [
                'inventory_item_id' => $item->id,
                'marketplace_account_id' => $account->id,
            ],
            [
                'user_id' => $item->user_id,
                'channel' => $account->channel,
                'sku' => $item->sku,
                'status' => ListingStatus::Pending,
                'issues' => null,
                'listed_price' => $item->list_price,
                'listed_quantity' => $item->quantity,
            ],
        );

        PublishListingJob::dispatch($listing->id);

        return $listing;
    }
}
