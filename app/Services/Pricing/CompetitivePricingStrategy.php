<?php

namespace App\Services\Pricing;

use App\Channels\ChannelManager;
use App\Channels\Data\Money;

/**
 * Suggests a price from a marketplace's live competitive offers. Requires a
 * connected account and an ASIN/identifier match in the context; sits the
 * seller's undercut amount below the lowest competitive price. Returns null when
 * no live price is available (PricingService then falls back to the default).
 */
class CompetitivePricingStrategy implements PricingStrategy
{
    public const KEY = 'competitive';

    public function __construct(private readonly ChannelManager $channels) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function suggest(PricingContext $context): ?Money
    {
        if ($context->account === null || $context->match === null) {
            return null;
        }

        $price = $this->channels->for($context->account)
            ->getCompetitivePrice($context->account, $context->match, $context->condition);

        if ($price === null) {
            return null;
        }

        $undercut = $context->rule->undercut_amount;

        return $undercut ? $price->subtract($undercut) : $price;
    }
}
