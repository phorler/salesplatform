<?php

namespace Tests\Feature;

use App\Channels\Data\ChannelOrder;
use App\Channels\Data\ChannelOrderItem;
use App\Channels\Data\Money;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\MarketplaceAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesFakeChannel;
use Tests\TestCase;

class OrderSyncTest extends TestCase
{
    use RefreshDatabase;
    use UsesFakeChannel;

    private function order(string $sku, int $qty = 1): ChannelOrder
    {
        return new ChannelOrder(
            externalOrderId: 'AMZ-100',
            purchasedAt: CarbonImmutable::parse('2026-06-20T10:00:00Z'),
            buyerMarketplace: 'A1F83G8C2ARO7P',
            items: [new ChannelOrderItem('ITEM-1', $sku, $qty, Money::of(10), Money::of(1.50))],
        );
    }

    private function seedItem(int $stock): array
    {
        $user = User::factory()->create();
        $account = MarketplaceAccount::factory()->create(['user_id' => $user->id]);
        $item = InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'sku' => 'SKU-SOLD',
            'quantity' => $stock,
            'cost' => 2.00,
        ]);

        return [$account, $item];
    }

    public function test_sync_records_sale_and_decrements_stock(): void
    {
        $fake = $this->bindFakeChannel();
        [$account, $item] = $this->seedItem(stock: 3);
        $fake->orders = [$this->order('SKU-SOLD', 1)];

        $new = app(OrderSyncService::class)->sync($account);

        $this->assertSame(1, $new);
        $this->assertDatabaseHas('sales', [
            'external_order_item_id' => 'ITEM-1',
            'channel' => 'amazon',
            'quantity' => 1,
            'sale_price' => '10.00',
        ]);
        $this->assertSame(2, $item->refresh()->quantity);
        $this->assertNotNull($account->refresh()->orders_synced_at);
    }

    public function test_sync_is_idempotent(): void
    {
        $fake = $this->bindFakeChannel();
        [$account, $item] = $this->seedItem(stock: 3);
        $fake->orders = [$this->order('SKU-SOLD', 1)];

        $this->assertSame(1, app(OrderSyncService::class)->sync($account));
        $this->assertSame(0, app(OrderSyncService::class)->sync($account));
        $this->assertSame(1, $item->refresh()->sales()->count());
    }

    public function test_sync_marks_item_sold_when_stock_exhausted(): void
    {
        $fake = $this->bindFakeChannel();
        [$account, $item] = $this->seedItem(stock: 1);
        $fake->orders = [$this->order('SKU-SOLD', 1)];

        app(OrderSyncService::class)->sync($account);

        $item->refresh();
        $this->assertSame(0, $item->quantity);
        $this->assertSame(InventoryStatus::Sold, $item->status);
    }

    public function test_sync_command_runs_for_connected_accounts(): void
    {
        $this->bindFakeChannel();
        $this->seedItem(stock: 2);

        $this->artisan('marketplace:sync-orders')->assertSuccessful();
    }
}
