<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Amazon\AmazonOfferScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonOfferScraperTest extends TestCase
{
    use RefreshDatabase;

    private function sampleHtml(): string
    {
        return file_get_contents(base_path('tests/Fixtures/amazon_offer_sample.html'));
    }

    public function test_parse_extracts_featured_and_from_prices(): void
    {
        $summary = (new AmazonOfferScraper)->parse($this->sampleHtml(), '0140328726');

        $this->assertSame('67.69', $summary->featured->amount);   // buy-box
        $this->assertSame('12.55', $summary->lowestNew->amount);  // "New from £12.55"
        $this->assertSame('3.40', $summary->lowestUsed->amount);  // "Used from £3.40"
        $this->assertSame('3.40', $summary->lowestAny->amount);   // first "from £X"
        $this->assertStringContainsString('offer-listing/0140328726', $summary->url);
        $this->assertTrue($summary->hasAnyPrice());
    }

    public function test_for_isbn_fetches_caches_and_returns_summary(): void
    {
        config(['amazon.scrape_offers' => true]);
        Http::fake(['www.amazon.co.uk/dp/*' => Http::response($this->sampleHtml(), 200)]);

        $scraper = app(AmazonOfferScraper::class);
        $summary = $scraper->forIsbn('9780140328721'); // -> ISBN-10 0140328726

        $this->assertNotNull($summary);
        $this->assertSame('67.69', $summary->featured->amount);

        // Second call is served from cache (no extra HTTP request).
        $scraper->forIsbn('9780140328721');
        Http::assertSentCount(1);
    }

    public function test_disabled_returns_null_and_never_hits_amazon(): void
    {
        config(['amazon.scrape_offers' => false]);
        Http::fake();

        $this->assertNull(app(AmazonOfferScraper::class)->forIsbn('9780140328721'));
        Http::assertNothingSent();
    }

    public function test_captcha_response_yields_null(): void
    {
        config(['amazon.scrape_offers' => true]);
        Http::fake(['www.amazon.co.uk/dp/*' => Http::response('<html>Robot Check — Enter the characters you see below</html>', 200)]);

        $this->assertNull(app(AmazonOfferScraper::class)->forIsbn('9780140328721'));
    }

    public function test_endpoint_returns_offer_json_for_owner(): void
    {
        config(['amazon.scrape_offers' => true]);
        Http::fake(['www.amazon.co.uk/dp/*' => Http::response($this->sampleHtml(), 200)]);

        $user = User::factory()->create();
        $item = InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create(['isbn13' => '9780140328721'])->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('inventory.amazon-offers', $item))
            ->assertOk()
            ->assertJson(['featured' => '67.69', 'lowest_used' => '3.40']);
    }
}
