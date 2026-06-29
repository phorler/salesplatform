<?php

namespace App\Jobs;

use App\Channels\ChannelManager;
use App\Enums\InventoryStatus;
use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Polls a submitted listing until the channel reports it Active (or Error), with
 * a bounded number of attempts and backoff.
 */
class PollListingStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const MAX_ATTEMPTS = 6;

    public function __construct(public int $listingId, public int $attempt = 1) {}

    public function handle(ChannelManager $channels): void
    {
        $listing = Listing::withoutGlobalScopes()
            ->with(['marketplaceAccount', 'inventoryItem'])
            ->find($this->listingId);

        if (! $listing || ! $listing->marketplaceAccount || ! $listing->status->isOpen()) {
            return;
        }

        $result = $channels->for($listing->marketplaceAccount)->getListingStatus($listing);

        $listing->update([
            'status' => $result->status,
            'issues' => $result->issues ?: null,
            'external_id' => $result->externalId ?? $listing->external_id,
            'status_checked_at' => now(),
        ]);

        if ($result->status === ListingStatus::Active) {
            $listing->inventoryItem?->update(['status' => InventoryStatus::Listed]);

            return;
        }

        if ($result->status === ListingStatus::Pending && $this->attempt < self::MAX_ATTEMPTS) {
            PollListingStatusJob::dispatch($listing->id, $this->attempt + 1)->delay(now()->addSeconds(60));
        }
    }
}
