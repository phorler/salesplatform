<?php

namespace App\Services\Keepa;

use App\Channels\Data\Money;
use App\Enums\Condition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Keepa API client for Amazon book pricing — a ToS-safe alternative to
 * the SP-API (and to scraping) while SP-API access is pending. Looks up by
 * ISBN-13 directly; returns the lowest new/used price and sales rank.
 *
 * @see https://keepa.com/#!api
 */
class KeepaClient
{
    private const ENDPOINT = 'https://api.keepa.com/product';

    // Keepa CSV/stats indices.
    private const AMAZON = 0;

    private const NEW = 1;

    private const USED = 2;

    private const SALES_RANK = 3;

    public function isConfigured(): bool
    {
        return (bool) config('services.keepa.key');
    }

    /** Lowest competitive price for a condition, or null if unavailable. */
    public function lowestPrice(string $isbn13, Condition $condition): ?Money
    {
        $snapshot = $this->snapshot($isbn13);
        if (! $snapshot) {
            return null;
        }

        return $condition === Condition::New
            ? $snapshot->newPrice
            : ($snapshot->usedPrice ?? $snapshot->newPrice);
    }

    /** Full market snapshot for a book, or null on miss/failure. */
    public function snapshot(string $isbn13): ?KeepaSnapshot
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get(self::ENDPOINT, [
                'key' => config('services.keepa.key'),
                'domain' => config('services.keepa.domain', 2),
                'code' => $isbn13,
                'stats' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Keepa request failed', ['isbn' => $isbn13, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->parse($response->json());
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function parse(array $json): ?KeepaSnapshot
    {
        $product = $json['products'][0] ?? null;
        if (! is_array($product)) {
            return null;
        }

        $current = $product['stats']['current'] ?? [];
        $value = fn (int $i) => (isset($current[$i]) && $current[$i] >= 0) ? $current[$i] : null;
        $price = function (int $i) use ($value): ?Money {
            $cents = $value($i);

            return $cents === null ? null : Money::of($cents / 100, $this->currency());
        };

        return new KeepaSnapshot(
            asin: $product['asin'] ?? null,
            newPrice: $price(self::NEW) ?? $price(self::AMAZON),
            usedPrice: $price(self::USED),
            salesRank: $value(self::SALES_RANK),
        );
    }

    private function currency(): string
    {
        return match ((int) config('services.keepa.domain', 2)) {
            1 => 'USD',
            2 => 'GBP',
            3, 4, 5, 8, 9 => 'EUR',
            default => 'GBP',
        };
    }
}
