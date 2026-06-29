<?php

namespace Tests\Feature;

use App\Channels\Data\ChannelMatch;
use App\Channels\Data\Money;
use App\Enums\Condition;
use App\Models\MarketplaceAccount;
use App\Models\PricingRule;
use App\Services\Pricing\CompetitivePricingStrategy;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use Tests\Support\FakeChannel;
use Tests\Support\UsesFakeChannel;
use Tests\TestCase;

class CompetitivePricingTest extends TestCase
{
    use UsesFakeChannel;

    public function test_competitive_strategy_undercuts_lowest_price(): void
    {
        $fake = new FakeChannel;
        $fake->competitivePrice = Money::of(12.00);
        $this->bindFakeChannel($fake);

        $rule = new PricingRule([
            'strategy' => CompetitivePricingStrategy::KEY,
            'undercut_amount' => '0.50',
            'multipliers' => [],
        ]);

        $price = app(PricingService::class)->suggest(new PricingContext(
            condition: Condition::Good,
            rule: $rule,
            account: new MarketplaceAccount(['channel' => 'amazon']),
            match: new ChannelMatch('9780140328721', 'B0X'),
        ));

        $this->assertSame('11.50', $price->amount);
    }

    public function test_returns_null_without_account_or_match(): void
    {
        $this->bindFakeChannel((function () {
            $f = new FakeChannel;
            $f->competitivePrice = Money::of(12.00);

            return $f;
        })());

        $rule = new PricingRule(['strategy' => CompetitivePricingStrategy::KEY, 'multipliers' => []]);

        $this->assertNull(app(PricingService::class)->suggest(new PricingContext(
            condition: Condition::Good,
            rule: $rule,
        )));
    }
}
