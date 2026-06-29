<?php

namespace App\Http\Controllers;

use App\Channels\ChannelManager;
use App\Enums\MarketplaceAccountStatus;
use App\Models\InventoryItem;
use App\Models\MarketplaceAccount;
use App\Services\Keepa\KeepaClient;
use App\Services\ListingService;
use App\Services\Pricing\CompetitivePricingStrategy;
use App\Services\Pricing\KeepaPricingStrategy;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listings,
        private readonly ChannelManager $channels,
        private readonly PricingService $pricing,
        private readonly KeepaClient $keepa,
    ) {}

    /** Queue publishing this item to the seller's connected Amazon account. */
    public function store(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $account = $this->connectedAmazon($request);
        if (! $account) {
            return back()->withErrors(['publish' => 'Connect an Amazon account before publishing.']);
        }

        $this->listings->queuePublish($inventoryItem, $account);

        return back()->with('status', 'Publishing to Amazon — this can take a few minutes to go live.');
    }

    /**
     * Fetch a live competitive price and apply it to the item. Prefers Keepa
     * (ISBN-based, no Amazon account needed); falls back to the SP-API competitive
     * strategy when a seller account is connected.
     */
    public function refreshPrice(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->loadMissing('product');
        $rule = $this->pricing->ruleFor($request->user());

        if ($this->keepa->isConfigured()) {
            $rule->strategy = KeepaPricingStrategy::KEY;
            $context = new PricingContext(
                condition: $inventoryItem->condition,
                rule: $rule,
                item: $inventoryItem,
            );
            $source = 'Keepa';
        } else {
            $account = $this->connectedAmazon($request);
            if (! $account) {
                return back()->withErrors(['price' => 'Set a Keepa API key or connect an Amazon account to get live prices.']);
            }

            $match = $this->channels->for($account)->matchProduct($account, $inventoryItem->product->isbn13);
            if (! $match) {
                return back()->withErrors(['price' => 'Could not find this book in the Amazon catalogue.']);
            }

            $rule->strategy = CompetitivePricingStrategy::KEY;
            $context = new PricingContext(
                condition: $inventoryItem->condition,
                rule: $rule,
                item: $inventoryItem,
                account: $account,
                match: $match,
            );
            $source = 'Amazon';
        }

        $price = $this->pricing->suggest($context);
        if (! $price) {
            return back()->withErrors(['price' => 'No live competitive price is available right now.']);
        }

        $inventoryItem->update(['suggested_price' => $price->amount, 'list_price' => $price->amount]);

        return back()->with('status', "Updated price from live {$source} data: £{$price->amount}.");
    }

    private function connectedAmazon(Request $request): ?MarketplaceAccount
    {
        return $request->user()->marketplaceAccounts()
            ->where('channel', 'amazon')
            ->where('status', MarketplaceAccountStatus::Connected)
            ->first();
    }
}
