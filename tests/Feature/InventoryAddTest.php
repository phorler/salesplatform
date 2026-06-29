<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryAddTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9780140328721';

    private function fakeOpenLibrary(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response([
                'ISBN:'.self::ISBN => [
                    'title' => 'Fantastic Mr Fox',
                    'authors' => [['name' => 'Roald Dahl']],
                    'publishers' => [['name' => 'Penguin']],
                    'publish_date' => '1988',
                    'number_of_pages' => 96,
                    'cover' => ['large' => 'https://covers.openlibrary.org/b/id/1-L.jpg'],
                    'identifiers' => ['isbn_10' => ['0140328726'], 'isbn_13' => [self::ISBN]],
                ],
            ], 200),
        ]);
    }

    public function test_lookup_returns_book_data_and_caches_the_product(): void
    {
        $this->fakeOpenLibrary();

        $this->actingAs(User::factory()->create())
            ->postJson(route('inventory.lookup'), ['isbn' => self::ISBN])
            ->assertOk()
            ->assertJson(['isbn13' => self::ISBN, 'title' => 'Fantastic Mr Fox', 'authors' => 'Roald Dahl']);

        $this->assertDatabaseHas('products', ['isbn13' => self::ISBN, 'title' => 'Fantastic Mr Fox']);
    }

    public function test_lookup_rejects_invalid_isbn(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('inventory.lookup'), ['isbn' => '123'])
            ->assertStatus(422);
    }

    public function test_lookup_returns_404_when_book_not_found(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response([], 200)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('inventory.lookup'), ['isbn' => self::ISBN])
            ->assertStatus(404);
    }

    public function test_store_creates_inventory_item_with_condition_based_suggested_price(): void
    {
        $this->fakeOpenLibrary();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('inventory.store'), [
            'isbn' => self::ISBN,
            'condition' => 'good',      // default multiplier 0.60
            'quantity' => 1,
            'reference_price' => 10,
        ])->assertRedirect(route('inventory.index'));

        $item = InventoryItem::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('6.00', (string) $item->suggested_price);   // 10 * 0.60
        $this->assertSame('6.00', (string) $item->list_price);        // falls back to suggested
        $this->assertSame(self::ISBN, $item->product->isbn13);
        $this->assertNotEmpty($item->sku);
    }

    public function test_isbn_lookup_is_cached_and_not_refetched(): void
    {
        $this->fakeOpenLibrary();
        $user = User::factory()->create();

        // Pre-cache the product, then ensure a lookup doesn't hit the API again.
        Product::factory()->create(['isbn13' => self::ISBN, 'fetched_at' => now()]);
        Http::fake(); // any HTTP call now would record; we assert none happen

        $this->actingAs($user)
            ->postJson(route('inventory.lookup'), ['isbn' => self::ISBN])
            ->assertOk();

        Http::assertNothingSent();
    }
}
