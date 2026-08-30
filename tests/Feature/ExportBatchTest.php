<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\ExportBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportBatchTest extends TestCase
{
    use RefreshDatabase;

    private function ready(User $user, array $attrs = []): InventoryItem
    {
        return InventoryItem::factory()->create(array_merge([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create(['isbn13' => '9780140328721', 'isbn10' => '0140328726'])->id,
            'condition' => Condition::Good,
            'list_price' => 5.50,
            'quantity' => 1,
            'status' => InventoryStatus::ReadyToList,
        ], $attrs));
    }

    public function test_saving_a_graded_priced_draft_becomes_ready_to_list(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'condition' => Condition::Good,
            'status' => InventoryStatus::Draft,
        ]);

        $this->actingAs($user)->put(route('inventory.update', $item), [
            'condition' => 'good',
            'status' => InventoryStatus::Draft->value, // left as draft…
            'quantity' => 1,
            'list_price' => 6.00,                      // …but now priced
        ])->assertRedirect();

        $this->assertSame(InventoryStatus::ReadyToList, $item->refresh()->status);
    }

    public function test_export_creates_a_batch_of_only_ready_items_and_downloads(): void
    {
        $user = User::factory()->create();
        $this->ready($user, ['sku' => 'SKU-READY']);
        // Not ready — must be excluded.
        InventoryItem::factory()->create([
            'user_id' => $user->id, 'product_id' => Product::factory()->create()->id,
            'sku' => 'SKU-DRAFT', 'status' => InventoryStatus::Draft,
        ]);

        $response = $this->actingAs($user)->post(route('exports.store'));
        $response->assertOk();

        $csv = $response->getContent();
        $this->assertStringContainsString('SKU-READY', $csv);
        $this->assertStringNotContainsString('SKU-DRAFT', $csv);

        $batch = ExportBatch::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $batch->item_count);
        $this->assertNull($batch->marked_listed_at);
    }

    public function test_export_with_no_ready_items_reports_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('exports.store'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, ExportBatch::where('user_id', $user->id)->count());
    }

    public function test_batch_can_be_redownloaded_unchanged(): void
    {
        $user = User::factory()->create();
        $this->ready($user, ['sku' => 'SKU-DL']);

        $first = $this->actingAs($user)->post(route('exports.store'));
        $batch = ExportBatch::where('user_id', $user->id)->firstOrFail();

        $again = $this->actingAs($user)->get(route('exports.download', $batch));
        $again->assertOk();
        $this->assertSame($batch->csv, $again->getContent());
    }

    public function test_mark_listed_moves_items_to_listed(): void
    {
        $user = User::factory()->create();
        $item = $this->ready($user);
        $this->actingAs($user)->post(route('exports.store'));
        $batch = ExportBatch::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post(route('exports.mark-listed', $batch))
            ->assertRedirect(route('exports.index'));

        $this->assertSame(InventoryStatus::Listed, $item->refresh()->status);
        $this->assertNotNull($batch->refresh()->marked_listed_at);
    }

    public function test_exports_page_renders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('exports.index'))->assertOk();
    }
}
