<?php

namespace App\Services\Pricing;

use App\Channels\Data\Money;

/**
 * A pricing strategy turns a PricingContext into a suggested unit price (before
 * the floor/ceiling clamp, which PricingService applies centrally). Returns null
 * when it can't produce a suggestion (e.g. no reference/market price available).
 */
interface PricingStrategy
{
    public function key(): string;

    public function suggest(PricingContext $context): ?Money;
}
