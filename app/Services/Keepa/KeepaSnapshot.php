<?php

namespace App\Services\Keepa;

use App\Channels\Data\Money;

/**
 * A point-in-time read of a book's Amazon market from Keepa: the lowest new and
 * used prices and the sales rank.
 */
readonly class KeepaSnapshot
{
    public function __construct(
        public ?string $asin,
        public ?Money $newPrice,
        public ?Money $usedPrice,
        public ?int $salesRank,
    ) {}
}
