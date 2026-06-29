<?php

namespace App\Services;

use App\Channels\ChannelManager;
use App\Channels\Data\ChannelOrder;
use App\Channels\Data\ChannelOrderItem;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\MarketplaceAccount;
use App\Models\Sale;

/**
 * Pulls orders from a marketplace and reconciles them into sales. Idempotent:
 * the unique (channel, external_order_item_id) key means re-running never
 * double-counts. Matches each order line to inventory by SKU, records the sale,
 * and decrements stock.
 */
class OrderSyncService
{
    public function __construct(private readonly ChannelManager $channels) {}

    /** @return int number of new sales recorded */
    public function sync(MarketplaceAccount $account): int
    {
        $channel = $this->channels->for($account);
        $since = $account->orders_synced_at ?? now()->subDays(30);

        $new = 0;
        foreach ($channel->fetchOrders($account, $since) as $order) {
            foreach ($order->items as $item) {
                if ($this->reconcile($account, $order, $item)) {
                    $new++;
                }
            }
        }

        $account->update(['orders_synced_at' => now()]);

        return $new;
    }

    private function reconcile(MarketplaceAccount $account, ChannelOrder $order, ChannelOrderItem $line): bool
    {
        $alreadyRecorded = Sale::withoutGlobalScopes()
            ->where('channel', $account->channel)
            ->where('external_order_item_id', $line->externalOrderItemId)
            ->exists();

        if ($alreadyRecorded) {
            return false;
        }

        $inventory = InventoryItem::withoutGlobalScopes()
            ->where('user_id', $account->user_id)
            ->where('sku', $line->sku)
            ->first();

        $listing = $inventory?->listings()
            ->where('marketplace_account_id', $account->id)
            ->first();

        Sale::create([
            'user_id' => $account->user_id,
            'inventory_item_id' => $inventory?->id,
            'listing_id' => $listing?->id,
            'channel' => $account->channel,
            'external_order_id' => $order->externalOrderId,
            'external_order_item_id' => $line->externalOrderItemId,
            'quantity' => $line->quantity,
            'sale_price' => $line->unitPrice->amount,
            'fees' => $line->fees?->amount,
            'currency' => $line->unitPrice->currency,
            'buyer_marketplace' => $order->buyerMarketplace,
            'sold_at' => $order->purchasedAt,
            'raw' => $order->raw,
        ]);

        if ($inventory) {
            $remaining = max(0, $inventory->quantity - $line->quantity);
            $inventory->update([
                'quantity' => $remaining,
                'status' => $remaining === 0 ? InventoryStatus::Sold : $inventory->status,
            ]);
        }

        return true;
    }
}
