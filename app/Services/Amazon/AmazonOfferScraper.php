<?php

namespace App\Services\Amazon;

use App\Channels\Data\Money;
use App\Support\Isbn;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a lightweight offer summary (featured + lowest new/used) from a book's
 * public Amazon product page. Intended for low-volume, human-paced, on-demand use
 * (one fetch when a seller views an item), heavily cached, with graceful failure.
 *
 * This reads a public page for the seller's own pricing decisions; it does not
 * attempt the full per-seller offer list (that renders via JS). Disable entirely
 * with AMAZON_SCRAPE_OFFERS=false.
 *
 * Note: scraping is against Amazon's ToS; this is deliberately minimal, cached
 * and rate-limited, and falls back to the deep link on any block/CAPTCHA.
 */
class AmazonOfferScraper
{
    private const CACHE_TTL_HOURS = 12;

    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

    public function enabled(): bool
    {
        return (bool) config('amazon.scrape_offers', false);
    }

    /**
     * Offer summary for a book by ISBN-13, or null if disabled/unavailable.
     * Cached per ASIN so repeat views don't re-fetch.
     */
    public function forIsbn(string $isbn13): ?AmazonOfferSummary
    {
        if (! $this->enabled()) {
            return null;
        }

        $asin = Isbn::toIsbn10($isbn13);
        if ($asin === null) {
            return null; // 979-prefixed books have no ISBN-10/ASIN mapping
        }

        return Cache::remember(
            "amazon_offers:{$asin}",
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->fetch($asin),
        );
    }

    public function offerListingUrl(string $asin): string
    {
        return "https://www.amazon.co.uk/gp/offer-listing/{$asin}";
    }

    private function fetch(string $asin): ?AmazonOfferSummary
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::UA,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-GB,en;q=0.9',
            ])->timeout(8)->get("https://www.amazon.co.uk/dp/{$asin}");
        } catch (\Throwable $e) {
            Log::info('Amazon offer fetch failed', ['asin' => $asin, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        // A robot check means we've lost trust — bail (and don't cache a bad read).
        if (preg_match('/robot check|validateCaptcha|Enter the characters you see below/i', $body)) {
            return null;
        }

        $summary = $this->parse($body, $asin);

        // Don't cache an empty result (transient render); let the next view retry.
        return $summary->hasAnyPrice() ? $summary : null;
    }

    /**
     * Parse the featured price and buying-choices "from" prices out of a product
     * page. Public and pure so it can be unit-tested against a fixture.
     */
    public function parse(string $html, string $asin): AmazonOfferSummary
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        // Prepend a charset hint so DOMDocument treats the input as UTF-8;
        // otherwise it defaults to Latin-1 and mangles the £ sign.
        $doc->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $olp = $this->olpText($xp);

        return new AmazonOfferSummary(
            asin: $asin,
            url: $this->offerListingUrl($asin),
            featured: $this->featuredPrice($xp),
            lowestAny: $this->fromPrice($olp, null),
            lowestNew: $this->fromPrice($olp, 'New'),
            lowestUsed: $this->fromPrice($olp, 'Used'),
        );
    }

    private function featuredPrice(DOMXPath $xp): ?Money
    {
        foreach (['corePriceDisplay_desktop_feature_div', 'corePrice_feature_div', 'corePrice_desktop'] as $id) {
            $nodes = $xp->query('//*[@id="'.$id.'"]//span[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]');
            if ($nodes && $nodes->length > 0) {
                $money = $this->toMoney($nodes->item(0)->textContent);
                if ($money) {
                    return $money;
                }
            }
        }

        return null;
    }

    private function olpText(DOMXPath $xp): string
    {
        $node = $xp->query('//*[@id="olpLinkWidget_feature_div"]')->item(0)
            ?? $xp->query('//*[@id="olp_feature_div"]')->item(0);

        return $node ? preg_replace('/\s+/', ' ', $node->textContent) : '';
    }

    /**
     * Pull a "…from £X.XX" price out of the buying-choices text. When $condition
     * is given, require it to precede "from" (e.g. "Used from £3.40").
     */
    private function fromPrice(string $text, ?string $condition): ?Money
    {
        if ($text === '') {
            return null;
        }

        // Condition must sit directly before "from" (the "New from £12.55" row),
        // so the combined "Other Used, New, Collectible from £X" summary — which
        // isn't a per-condition figure — doesn't get mis-attributed.
        $pattern = $condition
            ? '/\b'.preg_quote($condition, '/').'\s+from\s*£([0-9][0-9.,]*)/i'
            : '/from\s*£([0-9][0-9.,]*)/i';

        return preg_match($pattern, $text, $m) ? Money::of($this->num($m[1])) : null;
    }

    private function toMoney(string $raw): ?Money
    {
        return preg_match('/£\s*([0-9][0-9.,]*)/', $raw, $m) ? Money::of($this->num($m[1])) : null;
    }

    private function num(string $raw): string
    {
        return str_replace(',', '', trim($raw));
    }
}
