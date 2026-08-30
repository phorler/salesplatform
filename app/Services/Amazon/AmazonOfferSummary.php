<?php

namespace App\Services\Amazon;

use App\Channels\Data\Money;

/**
 * A lightweight snapshot of a book's Amazon offers, parsed from the public
 * product page: the featured (buy-box) price and the lowest "from" prices shown
 * in the More Buying Choices summary. Not the full per-seller list (that renders
 * via JS and isn't reliably scrapable server-side).
 */
readonly class AmazonOfferSummary
{
    public function __construct(
        public string $asin,
        public string $url,
        public ?Money $featured = null,
        public ?Money $lowestAny = null,
        public ?Money $lowestNew = null,
        public ?Money $lowestUsed = null,
    ) {}

    public function hasAnyPrice(): bool
    {
        return $this->featured || $this->lowestAny || $this->lowestNew || $this->lowestUsed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'asin' => $this->asin,
            'url' => $this->url,
            'featured' => $this->featured?->amount,
            'lowest_any' => $this->lowestAny?->amount,
            'lowest_new' => $this->lowestNew?->amount,
            'lowest_used' => $this->lowestUsed?->amount,
        ];
    }
}
