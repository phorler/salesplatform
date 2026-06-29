<?php

namespace App\Channels\Data;

/**
 * The result of matching a product identifier (e.g. ISBN) to a marketplace's
 * own catalog. For Amazon, externalId is the ASIN and productType is e.g. "BOOK".
 */
readonly class ChannelMatch
{
    public function __construct(
        public string $identifier,   // the ISBN/UPC we searched for
        public string $externalId,   // channel catalog id (ASIN)
        public ?string $productType = null,
        public ?string $title = null,
    ) {}
}
