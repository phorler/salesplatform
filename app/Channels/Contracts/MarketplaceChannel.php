<?php

namespace App\Channels\Contracts;

use App\Channels\Data\ChannelMatch;
use App\Channels\Data\ChannelOrder;
use App\Channels\Data\ListingStatusResult;
use App\Channels\Data\Money;
use App\Channels\Data\SubmissionResult;
use App\Enums\Condition;
use App\Models\Listing;
use App\Models\MarketplaceAccount;
use Carbon\CarbonInterface;

/**
 * Channel-agnostic, item-agnostic contract every marketplace adapter implements
 * (Amazon first; eBay/Facebook later). Services and UI depend only on this
 * interface, never on a concrete channel.
 */
interface MarketplaceChannel
{
    /** Stable key used to resolve this channel (e.g. 'amazon'). */
    public function key(): string;

    /** Match a product identifier (ISBN/UPC) to this channel's catalog. */
    public function matchProduct(MarketplaceAccount $account, string $identifier): ?ChannelMatch;

    /** Lowest competitive price for a matched item in the given condition, if available. */
    public function getCompetitivePrice(MarketplaceAccount $account, ChannelMatch $match, Condition $condition): ?Money;

    /** Submit (create/update) a listing offer. Usually async — see SubmissionResult. */
    public function publishListing(Listing $listing): SubmissionResult;

    /** Poll the current status of a previously submitted listing. */
    public function getListingStatus(Listing $listing): ListingStatusResult;

    /**
     * Pull orders updated since the given time for reconciliation.
     *
     * @return iterable<ChannelOrder>
     */
    public function fetchOrders(MarketplaceAccount $account, CarbonInterface $since): iterable;
}
