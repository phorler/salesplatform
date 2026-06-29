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
 * Submits a listing to its marketplace. Submission is usually asynchronous, so
 * on a Pending result we queue a status poll.
 */
class PublishListingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $listingId) {}

    public function handle(ChannelManager $channels): void
    {
        $listing = Listing::withoutGlobalScopes()
            ->with(['marketplaceAccount', 'inventoryItem.product'])
            ->find($this->listingId);

        if (! $listing || ! $listing->marketplaceAccount) {
            return;
        }

        $result = $channels->for($listing->marketplaceAccount)->publishListing($listing);

        $listing->update([
            'status' => $result->status,
            'submission_id' => $result->submissionId ?? $listing->submission_id,
            'external_id' => $result->externalId ?? $listing->external_id,
            'issues' => $result->issues ?: null,
            'status_checked_at' => now(),
        ]);

        if ($result->status === ListingStatus::Active) {
            $listing->inventoryItem?->update(['status' => InventoryStatus::Listed]);
        } elseif ($result->status === ListingStatus::Pending) {
            PollListingStatusJob::dispatch($listing->id)->delay(now()->addSeconds(30));
        }
    }
}
