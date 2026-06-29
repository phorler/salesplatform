<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_produces_amazon_inventory_loader_csv(): void
    {
        $user = User::factory()->create();
        $withIsbn10 = Product::factory()->create(['isbn13' => '9780140328721', 'isbn10' => '0140328726']);
        $noIsbn10 = Product::factory()->create(['isbn13' => '9780000000002', 'isbn10' => null]);

        InventoryItem::factory()->create([
            'user_id' => $user->id, 'product_id' => $withIsbn10->id,
            'condition' => Condition::Good, 'sku' => 'SKU-G', 'list_price' => 5.50,
            'quantity' => 2, 'condition_note' => 'shelf wear',
        ]);
        InventoryItem::factory()->create([
            'user_id' => $user->id, 'product_id' => $noIsbn10->id,
            'condition' => Condition::New, 'sku' => 'SKU-N', 'list_price' => 9.99, 'quantity' => 1,
        ]);
        // Out of stock — must be excluded.
        InventoryItem::factory()->create([
            'user_id' => $user->id, 'product_id' => $withIsbn10->id,
            'condition' => Condition::Good, 'sku' => 'SKU-ZERO', 'quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.export'));

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('sku,product-id,product-id-type,price,item-condition,quantity,add-delete', $csv);
        // ISBN-10 preferred, Good => 3
        $this->assertStringContainsString('SKU-G,0140328726,2,5.50,3,2,a,"shelf wear"', $csv);
        // Falls back to ISBN-13, New => 11
        $this->assertStringContainsString('SKU-N,9780000000002,2,9.99,11,1,a', $csv);
        // Out-of-stock excluded
        $this->assertStringNotContainsString('SKU-ZERO', $csv);
    }

    public function test_export_only_includes_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        InventoryItem::factory()->create(['user_id' => $user->id, 'product_id' => Product::factory()->create()->id, 'sku' => 'MINE']);
        InventoryItem::factory()->create(['user_id' => $other->id, 'product_id' => Product::factory()->create()->id, 'sku' => 'THEIRS']);

        $csv = $this->actingAs($user)->get(route('inventory.export'))->streamedContent();

        $this->assertStringContainsString('MINE', $csv);
        $this->assertStringNotContainsString('THEIRS', $csv);
    }
}
