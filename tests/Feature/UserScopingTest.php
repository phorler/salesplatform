<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_authenticated_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $product = Product::factory()->create();

        InventoryItem::factory()->count(2)->create(['user_id' => $alice->id, 'product_id' => $product->id]);
        InventoryItem::factory()->count(3)->create(['user_id' => $bob->id, 'product_id' => $product->id]);

        $this->actingAs($alice);
        $this->assertSame(2, InventoryItem::count(), 'Alice should only see her own items');

        $this->actingAs($bob);
        $this->assertSame(3, InventoryItem::count(), 'Bob should only see his own items');
    }

    public function test_user_id_is_stamped_automatically_on_create(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);

        $item = InventoryItem::create([
            'product_id' => $product->id,
            'sku' => 'SKU-AUTO-1',
            'condition' => Condition::Good,
        ]);

        $this->assertSame($user->id, $item->user_id);
    }

    public function test_without_authentication_no_user_scope_is_applied(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->count(4)->create(['product_id' => $product->id]);

        // Console/queue context: no auth, so all rows are visible (jobs scope explicitly).
        $this->assertSame(4, InventoryItem::count());
    }
}
