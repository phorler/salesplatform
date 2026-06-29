<?php

namespace App\Services\Pricing;

use App\Channels\Data\Money;
use App\Models\PricingRule;
use App\Models\User;

/**
 * Resolves the configured pricing strategy, gets a suggestion, and applies the
 * seller's floor/ceiling clamp. Strategies are registered in config/pricing.php;
 * if the configured one is unavailable we fall back to the default (manual).
 */
class PricingService
{
    /**
     * @param  array<string, PricingStrategy>  $strategies
     */
    public function __construct(
        protected array $strategies,
        protected string $default = ManualMultiplierStrategy::KEY,
    ) {}

    public function suggest(PricingContext $context): ?Money
    {
        $price = $this->resolve($context->rule->strategy)->suggest($context);

        return $price === null ? null : $this->clamp($price, $context->rule);
    }

    /** Get (or lazily create with sensible defaults) a user's pricing rule. */
    public function ruleFor(User $user): PricingRule
    {
        return $user->pricingRule()->firstOrCreate([], PricingRule::defaults());
    }

    protected function resolve(?string $key): PricingStrategy
    {
        return $this->strategies[$key] ?? $this->strategies[$this->default];
    }

    protected function clamp(Money $price, PricingRule $rule): Money
    {
        if ($rule->price_floor !== null) {
            $floor = new Money((string) $rule->price_floor, $price->currency);
            if ($price->isLessThan($floor)) {
                return $floor;
            }
        }

        if ($rule->price_ceiling !== null) {
            $ceiling = new Money((string) $rule->price_ceiling, $price->currency);
            if ($ceiling->isLessThan($price)) {
                return $ceiling;
            }
        }

        return $price;
    }
}
