<?php

namespace App\Services\Pricing;

use App\Channels\Data\ChannelMatch;
use App\Channels\Data\Money;
use App\Enums\Condition;
use App\Models\InventoryItem;
use App\Models\MarketplaceAccount;
use App\Models\PricingRule;

/**
 * Everything a pricing strategy might need to suggest a price. Manual strategies
 * use the reference price; the competitive strategy (Milestone 6) uses the
 * account + match to fetch live market prices.
 */
readonly class PricingContext
{
    public function __construct(
        public Condition $condition,
        public PricingRule $rule,
        public ?Money $referencePrice = null,
        public ?InventoryItem $item = null,
        public ?MarketplaceAccount $account = null,
        public ?ChannelMatch $match = null,
    ) {}
}
