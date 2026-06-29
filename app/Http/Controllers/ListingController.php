<?php

namespace App\Http\Controllers;

use App\Channels\ChannelManager;
use App\Enums\MarketplaceAccountStatus;
use App\Models\InventoryItem;
use App\Models\MarketplaceAccount;
use App\Services\ListingService;
use App\Services\Pricing\CompetitivePricingStrategy;
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

    /** Fetch a live competitive price from Amazon and apply it to the item. */
    public function refreshPrice(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $account = $this->connectedAmazon($request);
        if (! $account) {
            return back()->withErrors(['price' => 'Connect an Amazon account first.']);
        }

        $match = $this->channels->for($account)->matchProduct($account, $inventoryItem->product->isbn13);
        if (! $match) {
            return back()->withErrors(['price' => 'Could not find this book in the Amazon catalogue.']);
        }

        $rule = $this->pricing->ruleFor($request->user());
        $rule->strategy = CompetitivePricingStrategy::KEY; // force live pricing for this action

        $price = $this->pricing->suggest(new PricingContext(
            condition: $inventoryItem->condition,
            rule: $rule,
            item: $inventoryItem,
            account: $account,
            match: $match,
        ));

        if (! $price) {
            return back()->withErrors(['price' => 'No live competitive price is available right now.']);
        }

        $inventoryItem->update(['suggested_price' => $price->amount, 'list_price' => $price->amount]);

        return back()->with('status', "Updated price from live Amazon offers: £{$price->amount}.");
    }

    private function connectedAmazon(Request $request): ?MarketplaceAccount
    {
        return $request->user()->marketplaceAccounts()
            ->where('channel', 'amazon')
            ->where('status', MarketplaceAccountStatus::Connected)
            ->first();
    }
}
