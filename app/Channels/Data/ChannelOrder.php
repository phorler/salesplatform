<?php

namespace App\Channels\Data;

use Carbon\CarbonImmutable;

/**
 * A normalized order pulled from a channel, with one or more line items.
 */
readonly class ChannelOrder
{
    public function __construct(
        public string $externalOrderId,
        public CarbonImmutable $purchasedAt,
        public ?string $buyerMarketplace = null,
        /** @var array<int, ChannelOrderItem> */
        public array $items = [],
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {}
}
