<?php

namespace Tests\Unit;

use App\Channels\Data\Money;
use App\Enums\Condition;
use App\Models\PricingRule;
use App\Services\Pricing\ManualMultiplierStrategy;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private function service(): PricingService
    {
        return new PricingService(
            [ManualMultiplierStrategy::KEY => new ManualMultiplierStrategy],
            ManualMultiplierStrategy::KEY,
        );
    }

    private function rule(array $attributes = []): PricingRule
    {
        return new PricingRule(array_merge([
            'strategy' => ManualMultiplierStrategy::KEY,
            'multipliers' => ['good' => 0.6, 'new' => 1.0],
        ], $attributes));
    }

    public function test_applies_condition_multiplier(): void
    {
        $price = $this->service()->suggest(new PricingContext(
            condition: Condition::Good,
            rule: $this->rule(),
            referencePrice: Money::of(10),
        ));

        $this->assertSame('6.00', $price->amount);
    }

    public function test_clamps_to_floor(): void
    {
        $price = $this->service()->suggest(new PricingContext(
            condition: Condition::Good,
            rule: $this->rule(['price_floor' => '2.00']),
            referencePrice: Money::of(1), // 1 * 0.6 = 0.60, below floor
        ));

        $this->assertSame('2.00', $price->amount);
    }

    public function test_clamps_to_ceiling(): void
    {
        $price = $this->service()->suggest(new PricingContext(
            condition: Condition::New,
            rule: $this->rule(['price_ceiling' => '50.00']),
            referencePrice: Money::of(100), // 100 * 1.0 = 100, above ceiling
        ));

        $this->assertSame('50.00', $price->amount);
    }

    public function test_returns_null_without_reference_price(): void
    {
        $this->assertNull($this->service()->suggest(new PricingContext(
            condition: Condition::Good,
            rule: $this->rule(),
        )));
    }

    public function test_falls_back_to_default_strategy_when_unconfigured(): void
    {
        // Rule asks for a strategy that isn't registered → default (manual) used.
        $price = $this->service()->suggest(new PricingContext(
            condition: Condition::Good,
            rule: $this->rule(['strategy' => 'competitive']),
            referencePrice: Money::of(10),
        ));

        $this->assertSame('6.00', $price->amount);
    }
}
