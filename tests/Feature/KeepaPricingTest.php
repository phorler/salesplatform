<?php

namespace Tests\Feature;

use App\Channels\Data\ChannelMatch;
use App\Enums\Condition;
use App\Models\InventoryItem;
use App\Models\PriceObservation;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use App\Services\Keepa\KeepaClient;
use App\Services\Pricing\KeepaPricingStrategy;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeepaPricingTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9780140328721';

    /** Lowest new £12.99, lowest used £9.99, rank 4200 (Keepa prices are in cents). */
    private function fakeKeepa(): void
    {
        config(['services.keepa.key' => 'test-key']);
        Http::fake(['api.keepa.com/*' => Http::response([
            'products' => [[
                'asin' => 'B000FAKE',
                'stats' => ['current' => [-1, 1299, 999, 4200]],
            ]],
        ], 200)]);
    }

    public function test_parse_extracts_new_used_and_rank(): void
    {
        $snapshot = (new KeepaClient)->parse([
            'products' => [['asin' => 'B0X', 'stats' => ['current' => [-1, 1299, 999, 4200]]]],
        ]);

        $this->assertSame('12.99', $snapshot->newPrice->amount);
        $this->assertSame('9.99', $snapshot->usedPrice->amount);
        $this->assertSame(4200, $snapshot->salesRank);
        $this->assertSame('B0X', $snapshot->asin);
    }

    public function test_lowest_price_depends_on_condition(): void
    {
        $this->fakeKeepa();
        $keepa = app(KeepaClient::class);

        $this->assertSame('9.99', $keepa->lowestPrice(self::ISBN, Condition::Good)->amount);
        $this->assertSame('12.99', $keepa->lowestPrice(self::ISBN, Condition::New)->amount);
    }

    public function test_keepa_strategy_undercuts_lowest_used(): void
    {
        $this->fakeKeepa();

        $price = app(PricingService::class)->suggest(new PricingContext(
            condition: Condition::Good,
            rule: new PricingRule(['strategy' => KeepaPricingStrategy::KEY, 'undercut_amount' => '0.50', 'multipliers' => []]),
            match: new ChannelMatch(self::ISBN, 'B000FAKE'),
        ));

        $this->assertSame('9.49', $price->amount); // 9.99 - 0.50
    }

    public function test_refresh_price_endpoint_uses_keepa_without_an_amazon_account(): void
    {
        $this->fakeKeepa();
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create(['isbn13' => self::ISBN])->id,
            'condition' => Condition::Good,
        ]);

        $this->actingAs($user)
            ->post(route('listings.refresh-price', $item))
            ->assertRedirect()
            ->assertSessionHas('status');

        // Default rule undercut is 0.01, so 9.99 - 0.01.
        $this->assertSame('9.98', (string) $item->refresh()->list_price);
    }

    public function test_command_records_market_snapshot_for_in_stock_books(): void
    {
        $this->fakeKeepa();
        $user = User::factory()->create();
        $product = Product::factory()->create(['isbn13' => self::ISBN]);
        InventoryItem::factory()->create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->artisan('keepa:refresh-prices', ['--sleep' => 0])->assertSuccessful();

        $this->assertDatabaseHas('price_observations', [
            'product_id' => $product->id,
            'new_price' => '12.99',
            'used_price' => '9.99',
            'sales_rank' => 4200,
        ]);
    }

    public function test_command_skips_when_keepa_not_configured(): void
    {
        config(['services.keepa.key' => null]);
        $user = User::factory()->create();
        InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ]);

        $this->artisan('keepa:refresh-prices')->assertSuccessful();

        $this->assertSame(0, PriceObservation::count());
    }
}
