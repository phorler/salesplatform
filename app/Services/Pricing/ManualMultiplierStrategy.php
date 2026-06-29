<?php

namespace App\Services\Pricing;

use App\Channels\Data\Money;

/**
 * Suggests a price by applying the seller's per-condition multiplier to a
 * reference/market price they provide. Works offline with no channel access.
 */
class ManualMultiplierStrategy implements PricingStrategy
{
    public const KEY = 'manual_multiplier';

    public function key(): string
    {
        return self::KEY;
    }

    public function suggest(PricingContext $context): ?Money
    {
        if ($context->referencePrice === null) {
            return null;
        }

        $multiplier = $context->rule->multiplierFor($context->condition);

        return $context->referencePrice->multiply($multiplier);
    }
}
