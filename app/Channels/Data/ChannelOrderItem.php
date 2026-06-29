<?php

namespace App\Channels\Data;

readonly class ChannelOrderItem
{
    public function __construct(
        public string $externalOrderItemId,
        public string $sku,
        public int $quantity,
        public Money $unitPrice,
        public ?Money $fees = null,
    ) {}
}
