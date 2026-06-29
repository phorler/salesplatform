<?php

namespace App\Services\Amazon;

use App\Enums\InventoryStatus;
use App\Enums\ListingStatus;
use App\Enums\MarketplaceAccountStatus;
use App\Models\Listing;
use App\Models\MarketplaceAccount;
use App\Models\User;
use SellingPartnerApi\Enums\Marketplace;

/**
 * Imports an Amazon "All Listings" / "Active Listings" / "Open Listings" report
 * (downloaded from Seller Central → Reports → Inventory Reports) to update
 * listing status, price, quantity and ASIN — a manual stand-in for the SP-API
 * while access is pending.
 *
 * Columns are matched by (normalised) header name, so it tolerates the column
 * differences between the report variants and either tab- or comma-delimited files.
 */
class AmazonListingsReportImporter
{
    /**
     * @return array{matched:int, unmatched:int, unmatched_skus:array<int,string>}
     */
    public function import(User $user, string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if (count($lines) < 2) {
            return ['matched' => 0, 'unmatched' => 0, 'unmatched_skus' => []];
        }

        // Strip a UTF-8 BOM and detect the delimiter from the header line.
        $lines[0] = ltrim($lines[0], "\xEF\xBB\xBF");
        $delimiter = substr_count($lines[0], "\t") >= substr_count($lines[0], ',') ? "\t" : ',';

        $header = array_map(fn ($h) => $this->normalize($h), str_getcsv($lines[0], $delimiter));
        $index = array_flip($header);

        $skuPos = $index['sellersku'] ?? $index['sku'] ?? null;
        if ($skuPos === null) {
            return ['matched' => 0, 'unmatched' => 0, 'unmatched_skus' => [], 'error' => 'No seller-sku column found.'];
        }

        $account = $this->amazonAccount($user);

        $matched = 0;
        $unmatched = 0;
        $unmatchedSkus = [];

        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line, $delimiter);
            $sku = trim((string) ($cols[$skuPos] ?? ''));
            if ($sku === '') {
                continue;
            }

            $get = fn (string $name) => isset($index[$name]) ? trim((string) ($cols[$index[$name]] ?? '')) : null;

            $item = $user->inventoryItems()->where('sku', $sku)->first();
            if (! $item) {
                $unmatched++;
                if (count($unmatchedSkus) < 25) {
                    $unmatchedSkus[] = $sku;
                }

                continue;
            }

            $status = $this->mapStatus($get('status'));
            $quantity = $get('quantity');
            $price = $get('price');

            Listing::updateOrCreate(
                ['inventory_item_id' => $item->id, 'marketplace_account_id' => $account->id],
                [
                    'user_id' => $user->id,
                    'channel' => 'amazon',
                    'sku' => $sku,
                    'external_id' => ($get('asin1') ?: $get('asin')) ?: null,
                    'status' => $status,
                    'listed_price' => is_numeric($price) ? $price : null,
                    'listed_quantity' => is_numeric($quantity) ? (int) $quantity : null,
                    'status_checked_at' => now(),
                ],
            );

            if ($status === ListingStatus::Active) {
                $item->update(['status' => InventoryStatus::Listed]);
            }

            $matched++;
        }

        return ['matched' => $matched, 'unmatched' => $unmatched, 'unmatched_skus' => $unmatchedSkus];
    }

    /**
     * The seller's Amazon account row; created (disconnected) if absent so imported
     * listings have a home. A later OAuth connect updates this same row.
     */
    private function amazonAccount(User $user): MarketplaceAccount
    {
        $marketplace = Marketplace::fromCountryCode(config('amazon.marketplace', 'GB'));

        return $user->marketplaceAccounts()->firstOrCreate(
            ['channel' => 'amazon', 'marketplace_id' => $marketplace->value],
            [
                'label' => 'Amazon '.$marketplace->name,
                'region' => strtolower(Marketplace::toRegion($marketplace)->name),
                'status' => MarketplaceAccountStatus::Disconnected,
            ],
        );
    }

    private function mapStatus(?string $raw): ListingStatus
    {
        // Active/Open Listings reports have no status column — treat as active.
        return match (strtolower((string) $raw)) {
            '', 'active' => ListingStatus::Active,
            default => ListingStatus::Inactive,
        };
    }

    private function normalize(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($header))) ?? '';
    }
}
