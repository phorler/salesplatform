<?php

namespace Tests\Unit;

use App\Channels\AmazonChannel;
use App\Enums\ListingStatus;
use PHPUnit\Framework\TestCase;

class AmazonParsingTest extends TestCase
{
    private AmazonChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new AmazonChannel;
    }

    public function test_parse_lowest_price_picks_the_cheapest_landed_price(): void
    {
        $price = $this->channel->parseLowestPrice([
            'payload' => ['Summary' => ['LowestPrices' => [
                ['LandedPrice' => ['Amount' => 12.00, 'CurrencyCode' => 'GBP']],
                ['LandedPrice' => ['Amount' => 9.99, 'CurrencyCode' => 'GBP']],
            ]]],
        ]);

        $this->assertNotNull($price);
        $this->assertSame('9.99', $price->amount);
        $this->assertSame('GBP', $price->currency);
    }

    public function test_parse_lowest_price_null_when_no_offers(): void
    {
        $this->assertNull($this->channel->parseLowestPrice([]));
    }

    public function test_parse_submission_maps_accepted_to_pending(): void
    {
        $result = $this->channel->parseSubmission(['status' => 'ACCEPTED', 'submissionId' => 'S1'], 'B0X');

        $this->assertSame(ListingStatus::Pending, $result->status);
        $this->assertSame('S1', $result->submissionId);
        $this->assertSame('B0X', $result->externalId);
    }

    public function test_parse_submission_maps_invalid_to_error(): void
    {
        $result = $this->channel->parseSubmission([
            'status' => 'INVALID',
            'issues' => [['severity' => 'ERROR', 'message' => 'bad']],
        ]);

        $this->assertSame(ListingStatus::Error, $result->status);
    }

    public function test_parse_listing_status_active_when_buyable(): void
    {
        $result = $this->channel->parseListingStatus(['summaries' => [['status' => ['BUYABLE']]]]);
        $this->assertSame(ListingStatus::Active, $result->status);
    }

    public function test_parse_listing_status_error_on_error_issue(): void
    {
        $result = $this->channel->parseListingStatus(['issues' => [['severity' => 'ERROR']]]);
        $this->assertSame(ListingStatus::Error, $result->status);
    }

    public function test_parse_order_builds_line_items(): void
    {
        $order = $this->channel->parseOrder(
            ['AmazonOrderId' => 'AMZ-1', 'PurchaseDate' => '2026-06-20T10:00:00Z', 'MarketplaceId' => 'A1F83G8C2ARO7P'],
            ['payload' => ['OrderItems' => [[
                'OrderItemId' => 'OI-1',
                'SellerSKU' => 'SKU-1',
                'QuantityOrdered' => 2,
                'ItemPrice' => ['Amount' => 19.98, 'CurrencyCode' => 'GBP'],
                'ItemTax' => ['Amount' => 3.33, 'CurrencyCode' => 'GBP'],
            ]]]],
        );

        $this->assertSame('AMZ-1', $order->externalOrderId);
        $this->assertCount(1, $order->items);
        $this->assertSame('SKU-1', $order->items[0]->sku);
        $this->assertSame(2, $order->items[0]->quantity);
        $this->assertSame('19.98', $order->items[0]->unitPrice->amount);
        $this->assertSame('3.33', $order->items[0]->fees->amount);
    }
}
