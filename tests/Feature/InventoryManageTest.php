<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManageTest extends TestCase
{
    use RefreshDatabase;

    private function item(User $user): InventoryItem
    {
        return InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'condition' => Condition::Good,
        ]);
    }

    public function test_owner_can_update_an_item_and_recompute_suggested_price(): void
    {
        $user = User::factory()->create();
        $item = $this->item($user);

        $this->actingAs($user)->put(route('inventory.update', $item), [
            'condition' => 'very_good',   // default multiplier 0.75
            'status' => InventoryStatus::Listed->value,
            'quantity' => 3,
            'reference_price' => 20,
            'list_price' => 14.50,
        ])->assertRedirect(route('inventory.show', $item));

        $item->refresh();
        $this->assertSame('very_good', $item->condition->value);
        $this->assertSame(InventoryStatus::Listed, $item->status);
        $this->assertSame(3, $item->quantity);
        $this->assertSame('15.00', (string) $item->suggested_price); // 20 * 0.75
        $this->assertSame('14.50', (string) $item->list_price);
    }

    public function test_owner_can_delete_an_item(): void
    {
        $user = User::factory()->create();
        $item = $this->item($user);

        $this->actingAs($user)->delete(route('inventory.destroy', $item))
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_a_user_cannot_view_or_edit_another_users_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $item = $this->item($owner);

        $this->actingAs($intruder)->get(route('inventory.show', $item))->assertNotFound();
        $this->actingAs($intruder)->get(route('inventory.edit', $item))->assertNotFound();
        $this->actingAs($intruder)->delete(route('inventory.destroy', $item))->assertNotFound();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        InventoryItem::factory()->create(['user_id' => $user->id, 'product_id' => $product->id, 'status' => InventoryStatus::Draft]);
        InventoryItem::factory()->create(['user_id' => $user->id, 'product_id' => $product->id, 'status' => InventoryStatus::Sold]);

        $this->actingAs($user)
            ->get(route('inventory.index', ['status' => InventoryStatus::Sold->value]))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->count() === 1
                && $items->first()->status === InventoryStatus::Sold);
    }

    public function test_pricing_rules_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.pricing.update'), [
            'strategy' => 'manual_multiplier',
            'multipliers' => ['new' => 1, 'like_new' => 0.9, 'very_good' => 0.8, 'good' => 0.65, 'acceptable' => 0.5],
            'price_floor' => 2.99,
            'price_ceiling' => 80,
        ])->assertRedirect(route('settings.pricing.edit'));

        $rule = $user->pricingRule()->first();
        $this->assertSame('manual_multiplier', $rule->strategy);
        $this->assertEquals(0.65, $rule->multipliers['good']);
        $this->assertSame('2.99', (string) $rule->price_floor);
    }

    public function test_pricing_rules_reject_ceiling_below_floor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.pricing.update'), [
            'strategy' => 'manual_multiplier',
            'multipliers' => ['new' => 1, 'like_new' => 0.9, 'very_good' => 0.8, 'good' => 0.65, 'acceptable' => 0.5],
            'price_floor' => 50,
            'price_ceiling' => 10,
        ])->assertSessionHasErrors('price_ceiling');
    }

    public function test_pages_render_for_owner(): void
    {
        $user = User::factory()->create();
        $item = $this->item($user);

        $this->actingAs($user)->get(route('inventory.create'))->assertOk();
        $this->actingAs($user)->get(route('inventory.show', $item))->assertOk()->assertSee($item->sku);
        $this->actingAs($user)->get(route('inventory.edit', $item))->assertOk();
        $this->actingAs($user)->get(route('settings.pricing.edit'))->assertOk()->assertSee('Condition multipliers');
    }
}
