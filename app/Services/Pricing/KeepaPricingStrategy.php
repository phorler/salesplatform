<?php

namespace App\Services\Pricing;

use App\Channels\Data\Money;
use App\Services\Keepa\KeepaClient;

/**
 * Competitive pricing from Keepa's Amazon data (ISBN-based, no seller account or
 * ASIN match required). Undercuts the lowest competitive price by the seller's
 * configured amount; returns null when no live price is available.
 */
class KeepaPricingStrategy implements PricingStrategy
{
    public const KEY = 'keepa';

    public function __construct(private readonly KeepaClient $keepa) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function suggest(PricingContext $context): ?Money
    {
        $isbn = $context->item?->product?->isbn13 ?? $context->match?->identifier;
        if (! $isbn) {
            return null;
        }

        $price = $this->keepa->lowestPrice($isbn, $context->condition);
        if ($price === null) {
            return null;
        }

        $undercut = $context->rule->undercut_amount;

        return $undercut ? $price->subtract($undercut) : $price;
    }
}
