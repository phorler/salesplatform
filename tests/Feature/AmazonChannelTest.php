<?php

namespace Tests\Feature;

use App\Channels\AmazonChannel;
use App\Models\MarketplaceAccount;
use Tests\TestCase;

class AmazonChannelTest extends TestCase
{
    public function test_key_is_amazon(): void
    {
        $this->assertSame('amazon', (new AmazonChannel)->key());
    }

    public function test_match_product_returns_null_for_invalid_isbn(): void
    {
        $this->assertNull((new AmazonChannel)->matchProduct(new MarketplaceAccount, 'not-an-isbn'));
    }

    public function test_parse_catalog_match_extracts_asin_and_title(): void
    {
        $match = (new AmazonChannel)->parseCatalogMatch([
            'items' => [[
                'asin' => 'B00ASIN01',
                'summaries' => [['itemName' => 'Fantastic Mr Fox', 'itemClassification' => 'product']],
            ]],
        ], '9780140328721');

        $this->assertNotNull($match);
        $this->assertSame('B00ASIN01', $match->externalId);
        $this->assertSame('9780140328721', $match->identifier);
        $this->assertSame('Fantastic Mr Fox', $match->title);
    }

    public function test_parse_catalog_match_returns_null_when_no_items(): void
    {
        $this->assertNull((new AmazonChannel)->parseCatalogMatch([], '9780140328721'));
        $this->assertNull((new AmazonChannel)->parseCatalogMatch(['items' => []], '9780140328721'));
    }
}
