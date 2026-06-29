<?php

namespace Tests\Support;

use App\Channels\Contracts\MarketplaceChannel;
use App\Channels\Data\ChannelMatch;
use App\Channels\Data\ChannelOrder;
use App\Channels\Data\ListingStatusResult;
use App\Channels\Data\Money;
use App\Channels\Data\SubmissionResult;
use App\Enums\Condition;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\MarketplaceAccount;
use Carbon\CarbonInterface;

/**
 * Configurable in-memory MarketplaceChannel for tests. Lets us exercise the
 * services, jobs and controllers without touching Amazon.
 */
class FakeChannel implements MarketplaceChannel
{
    public ?ChannelMatch $match = null;

    public ?Money $competitivePrice = null;

    public ?SubmissionResult $submission = null;

    public ?ListingStatusResult $statusResult = null;

    /** @var array<int, ChannelOrder> */
    public array $orders = [];

    public function key(): string
    {
        return 'amazon';
    }

    public function matchProduct(MarketplaceAccount $account, string $identifier): ?ChannelMatch
    {
        return $this->match ?? new ChannelMatch($identifier, 'B0FAKEASIN', 'BOOK', 'Fake Book');
    }

    public function getCompetitivePrice(MarketplaceAccount $account, ChannelMatch $match, Condition $condition): ?Money
    {
        return $this->competitivePrice;
    }

    public function publishListing(Listing $listing): SubmissionResult
    {
        return $this->submission ?? new SubmissionResult(ListingStatus::Active, externalId: 'B0FAKEASIN');
    }

    public function getListingStatus(Listing $listing): ListingStatusResult
    {
        return $this->statusResult ?? new ListingStatusResult(ListingStatus::Active);
    }

    public function fetchOrders(MarketplaceAccount $account, CarbonInterface $since): iterable
    {
        return $this->orders;
    }
}
