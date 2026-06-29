<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Isbn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches book metadata from the free Open Library Books API (no key required)
 * and caches it in the global `products` table, keyed by ISBN-13.
 *
 * @see https://openlibrary.org/dev/docs/api/books
 */
class OpenLibraryService
{
    private const ENDPOINT = 'https://openlibrary.org/api/books';

    /** Re-fetch cached metadata older than this many days. */
    private const STALE_AFTER_DAYS = 30;

    /**
     * Return the cached/freshly-fetched Product for an ISBN, or null if the book
     * can't be found (or the identifier is invalid).
     */
    public function lookup(string $rawIsbn): ?Product
    {
        $isbn13 = Isbn::toIsbn13($rawIsbn);
        if ($isbn13 === null) {
            return null;
        }

        $existing = Product::where('isbn13', $isbn13)->first();
        if ($existing && ! $this->isStale($existing)) {
            return $existing;
        }

        $data = $this->fetch($isbn13);
        if ($data === null) {
            // Fall back to stale cache rather than losing a known book if the API
            // is unreachable.
            return $existing;
        }

        return Product::updateOrCreate(
            ['isbn13' => $isbn13],
            $data + ['fetched_at' => now()],
        );
    }

    /**
     * Call the Open Library API and normalise the response into product columns.
     * Returns null when the book isn't found or the request fails.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(string $isbn13): ?array
    {
        $key = "ISBN:{$isbn13}";

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'bibkeys' => $key,
                    'format' => 'json',
                    'jscmd' => 'data',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Open Library request failed', ['isbn' => $isbn13, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $book = $response->json($key);
        if (! is_array($book) || $book === []) {
            return null;
        }

        return $this->normalize($isbn13, $book);
    }

    /**
     * Map an Open Library `jscmd=data` record onto our product columns.
     *
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    private function normalize(string $isbn13, array $book): array
    {
        $identifiers = $book['identifiers'] ?? [];
        $cover = $book['cover'] ?? [];

        return [
            'isbn13' => $isbn13,
            'isbn10' => $identifiers['isbn_10'][0] ?? null,
            'title' => $book['title'] ?? 'Unknown title',
            'subtitle' => $book['subtitle'] ?? null,
            'authors' => array_values(array_filter(array_map(
                fn ($a) => is_array($a) ? ($a['name'] ?? null) : null,
                $book['authors'] ?? [],
            ))),
            'publisher' => $book['publishers'][0]['name'] ?? null,
            'published_year' => $this->parseYear($book['publish_date'] ?? null),
            'page_count' => isset($book['number_of_pages']) ? (int) $book['number_of_pages'] : null,
            'cover_url' => $cover['large'] ?? $cover['medium'] ?? $cover['small'] ?? null,
            'payload' => $book,
        ];
    }

    private function parseYear(?string $date): ?int
    {
        if ($date && preg_match('/(\d{4})/', $date, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function isStale(Product $product): bool
    {
        return $product->fetched_at === null
            || $product->fetched_at->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
