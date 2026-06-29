<?php

namespace App\Channels;

use App\Channels\Contracts\MarketplaceChannel;
use App\Channels\Data\ChannelMatch;
use App\Channels\Data\ChannelOrder;
use App\Channels\Data\ChannelOrderItem;
use App\Channels\Data\ListingStatusResult;
use App\Channels\Data\Money;
use App\Channels\Data\SubmissionResult;
use App\Enums\Condition;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\MarketplaceAccount;
use App\Support\Isbn;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\Enums\Marketplace;
use SellingPartnerApi\Enums\Region;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use SellingPartnerApi\Seller\SellerConnector;
use SellingPartnerApi\SellingPartnerApi;
use Throwable;

/**
 * Amazon adapter for the MarketplaceChannel contract, backed by
 * jlevers/selling-partner-api.
 *
 * The SP-API request methods are best-effort and should be validated against the
 * SP-API sandbox before going live (especially the putListingsItem attributes for
 * the BOOK product type). The pure parse* helpers are unit-tested. The connector
 * is built through a factory so tests can inject a fake without real credentials.
 */
class AmazonChannel implements MarketplaceChannel
{
    /** @var Closure(MarketplaceAccount): SellerConnector */
    private Closure $connectorFactory;

    public function __construct(?Closure $connectorFactory = null)
    {
        $this->connectorFactory = $connectorFactory ?? fn (MarketplaceAccount $a) => $this->defaultConnector($a);
    }

    public function key(): string
    {
        return 'amazon';
    }

    // ---- Catalogue matching -------------------------------------------------

    public function matchProduct(MarketplaceAccount $account, string $identifier): ?ChannelMatch
    {
        $isbn13 = Isbn::toIsbn13($identifier);
        if ($isbn13 === null) {
            return null;
        }

        try {
            $json = $this->connector($account)
                ->catalogItemsV20220401()
                ->searchCatalogItems(
                    marketplaceIds: [$this->marketplaceId()],
                    identifiers: [$isbn13],
                    identifiersType: 'ISBN',
                    includedData: ['summaries'],
                )
                ->json();
        } catch (Throwable) {
            return null;
        }

        return $this->parseCatalogMatch(is_array($json) ? $json : [], $isbn13);
    }

    /** @param array<string, mixed> $json */
    public function parseCatalogMatch(array $json, string $isbn13): ?ChannelMatch
    {
        $item = $json['items'][0] ?? null;
        $asin = $item['asin'] ?? null;
        if (! $asin) {
            return null;
        }

        return new ChannelMatch(
            identifier: $isbn13,
            externalId: $asin,
            productType: $item['summaries'][0]['itemClassification'] ?? null,
            title: $item['summaries'][0]['itemName'] ?? null,
        );
    }

    // ---- Competitive pricing ------------------------------------------------

    public function getCompetitivePrice(MarketplaceAccount $account, ChannelMatch $match, Condition $condition): ?Money
    {
        try {
            $json = $this->connector($account)
                ->productPricingV0()
                ->getItemOffers(
                    asin: $match->externalId,
                    marketplaceId: $this->marketplaceId(),
                    itemCondition: $this->offerCondition($condition),
                )
                ->json();
        } catch (Throwable) {
            return null;
        }

        return $this->parseLowestPrice(is_array($json) ? $json : []);
    }

    /** @param array<string, mixed> $json */
    public function parseLowestPrice(array $json): ?Money
    {
        $summary = $json['payload']['Summary'] ?? $json['Summary'] ?? [];
        $candidates = array_merge(
            $summary['LowestPrices'] ?? [],
            $summary['BuyBoxPrices'] ?? [],
        );

        $lowest = null;
        $currency = 'GBP';
        foreach ($candidates as $entry) {
            $price = $entry['LandedPrice'] ?? $entry['ListingPrice'] ?? null;
            if (! isset($price['Amount'])) {
                continue;
            }
            $amount = (float) $price['Amount'];
            if ($lowest === null || $amount < $lowest) {
                $lowest = $amount;
                $currency = $price['CurrencyCode'] ?? $currency;
            }
        }

        return $lowest === null ? null : Money::of($lowest, $currency);
    }

    // ---- Listing publish / status ------------------------------------------

    public function publishListing(Listing $listing): SubmissionResult
    {
        $account = $listing->marketplaceAccount;
        $item = $listing->inventoryItem;

        try {
            $asin = $listing->external_id
                ?: $this->matchProduct($account, $item->product->isbn13)?->externalId;

            $request = new ListingsItemPutRequest(
                productType: config('amazon.book_product_type', 'ABIS_BOOK'),
                attributes: $this->buildOfferAttributes($listing, $item, $asin),
            );

            $json = $this->connector($account)
                ->listingsItemsV20210801()
                ->putListingsItem(
                    sellerId: (string) $account->selling_partner_id,
                    sku: $item->sku,
                    listingsItemPutRequest: $request,
                    marketplaceIds: [$this->marketplaceId()],
                    issueLocale: 'en_GB',
                )
                ->json();

            return $this->parseSubmission(is_array($json) ? $json : [], $asin);
        } catch (Throwable $e) {
            return new SubmissionResult(ListingStatus::Error, issues: [['message' => $e->getMessage()]]);
        }
    }

    /** @param array<string, mixed> $json */
    public function parseSubmission(array $json, ?string $asin = null): SubmissionResult
    {
        $status = strtoupper((string) ($json['status'] ?? ''));
        $issues = $json['issues'] ?? [];

        $listingStatus = match ($status) {
            'ACCEPTED' => ListingStatus::Pending,
            'VALID' => ListingStatus::Pending,
            'INVALID' => ListingStatus::Error,
            default => $this->hasError($issues) ? ListingStatus::Error : ListingStatus::Pending,
        };

        return new SubmissionResult(
            status: $listingStatus,
            submissionId: $json['submissionId'] ?? null,
            externalId: $asin,
            issues: $issues,
        );
    }

    public function getListingStatus(Listing $listing): ListingStatusResult
    {
        $account = $listing->marketplaceAccount;

        try {
            $json = $this->connector($account)
                ->listingsItemsV20210801()
                ->getListingsItem(
                    sellerId: (string) $account->selling_partner_id,
                    sku: $listing->sku ?? $listing->inventoryItem->sku,
                    marketplaceIds: [$this->marketplaceId()],
                    includedData: ['summaries', 'issues'],
                )
                ->json();

            return $this->parseListingStatus(is_array($json) ? $json : []);
        } catch (Throwable $e) {
            return new ListingStatusResult(ListingStatus::Pending, issues: [['message' => $e->getMessage()]]);
        }
    }

    /** @param array<string, mixed> $json */
    public function parseListingStatus(array $json): ListingStatusResult
    {
        $issues = $json['issues'] ?? [];
        $statuses = $json['summaries'][0]['status'] ?? [];

        if ($this->hasError($issues)) {
            return new ListingStatusResult(ListingStatus::Error, issues: $issues);
        }

        if (in_array('BUYABLE', $statuses, true) || in_array('DISCOVERABLE', $statuses, true)) {
            return new ListingStatusResult(ListingStatus::Active, issues: $issues);
        }

        return new ListingStatusResult(ListingStatus::Pending, issues: $issues);
    }

    // ---- Orders -------------------------------------------------------------

    public function fetchOrders(MarketplaceAccount $account, CarbonInterface $since): iterable
    {
        $connector = $this->connector($account);

        $ordersJson = $connector->ordersV0()->getOrders(
            marketplaceIds: [$this->marketplaceId()],
            createdAfter: $since->toIso8601String(),
        )->json();

        foreach (($ordersJson['payload']['Orders'] ?? []) as $order) {
            $orderId = $order['AmazonOrderId'] ?? null;
            if (! $orderId) {
                continue;
            }

            $itemsJson = $connector->ordersV0()->getOrderItems($orderId)->json();

            yield $this->parseOrder($order, is_array($itemsJson) ? $itemsJson : []);
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $itemsJson
     */
    public function parseOrder(array $order, array $itemsJson): ChannelOrder
    {
        $items = [];
        foreach (($itemsJson['payload']['OrderItems'] ?? []) as $oi) {
            $price = $oi['ItemPrice'] ?? [];
            $fees = $oi['ItemTax'] ?? null;
            $items[] = new ChannelOrderItem(
                externalOrderItemId: (string) ($oi['OrderItemId'] ?? ''),
                sku: (string) ($oi['SellerSKU'] ?? ''),
                quantity: (int) ($oi['QuantityOrdered'] ?? 1),
                unitPrice: Money::of((float) ($price['Amount'] ?? 0), $price['CurrencyCode'] ?? 'GBP'),
                fees: isset($fees['Amount']) ? Money::of((float) $fees['Amount'], $fees['CurrencyCode'] ?? 'GBP') : null,
            );
        }

        return new ChannelOrder(
            externalOrderId: (string) ($order['AmazonOrderId'] ?? ''),
            purchasedAt: CarbonImmutable::parse($order['PurchaseDate'] ?? 'now'),
            buyerMarketplace: $order['MarketplaceId'] ?? null,
            items: $items,
            raw: $order,
        );
    }

    // ---- Helpers ------------------------------------------------------------

    public function marketplaceId(): string
    {
        return Marketplace::fromCountryCode(config('amazon.marketplace', 'GB'))->value;
    }

    private function offerCondition(Condition $condition): string
    {
        return $condition === Condition::New ? 'New' : 'Used';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOfferAttributes(Listing $listing, $item, ?string $asin): array
    {
        $mp = $this->marketplaceId();
        $price = (float) ($listing->listed_price ?? $item->list_price ?? 0);

        $attributes = [
            'condition_type' => [['value' => $item->condition->amazonConditionType(), 'marketplace_id' => $mp]],
            'fulfillment_availability' => [[
                'fulfillment_channel_code' => config('amazon.fulfillment_channel_code', 'DEFAULT'),
                'quantity' => (int) ($listing->listed_quantity ?? $item->quantity),
            ]],
            'purchasable_offer' => [[
                'currency' => $item->currency ?? 'GBP',
                'marketplace_id' => $mp,
                'our_price' => [['schedule' => [['value_with_tax' => $price]]]],
            ]],
        ];

        if ($asin) {
            $attributes['merchant_suggested_asin'] = [['value' => $asin, 'marketplace_id' => $mp]];
        }

        if ($item->condition_note) {
            $attributes['condition_note'] = [['value' => $item->condition_note, 'marketplace_id' => $mp]];
        }

        return $attributes;
    }

    /** @param array<int, mixed> $issues */
    private function hasError(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? null) === 'ERROR') {
                return true;
            }
        }

        return false;
    }

    protected function connector(MarketplaceAccount $account): SellerConnector
    {
        return ($this->connectorFactory)($account);
    }

    protected function defaultConnector(MarketplaceAccount $account): SellerConnector
    {
        return SellingPartnerApi::seller(
            config('amazon.lwa.client_id'),
            config('amazon.lwa.client_secret'),
            (string) $account->refresh_token,
            $this->endpoint(),
        );
    }

    protected function endpoint(): Endpoint
    {
        $region = Marketplace::toRegion(Marketplace::fromCountryCode(config('amazon.marketplace', 'GB')));
        $sandbox = (bool) config('amazon.sandbox', true);

        return match ($region) {
            Region::NA => $sandbox ? Endpoint::NA_SANDBOX : Endpoint::NA,
            Region::EU => $sandbox ? Endpoint::EU_SANDBOX : Endpoint::EU,
            Region::FE => $sandbox ? Endpoint::FE_SANDBOX : Endpoint::FE,
        };
    }
}
