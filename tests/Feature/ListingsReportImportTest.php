<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Enums\ListingStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Amazon\AmazonListingsReportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ListingsReportImportTest extends TestCase
{
    use RefreshDatabase;

    private function tsv(array $rows, array $header): string
    {
        $lines = [implode("\t", $header)];
        foreach ($rows as $row) {
            $lines[] = implode("\t", $row);
        }

        return implode("\n", $lines);
    }

    private function itemFor(User $user, string $sku): InventoryItem
    {
        return InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'sku' => $sku,
            'status' => InventoryStatus::Draft,
        ]);
    }

    public function test_imports_status_price_and_quantity_by_sku(): void
    {
        $user = User::factory()->create();
        $itemA = $this->itemFor($user, 'SKU-A');
        $itemB = $this->itemFor($user, 'SKU-B');

        $report = $this->tsv(
            header: ['seller-sku', 'asin1', 'price', 'quantity', 'status', 'fulfillment-channel'],
            rows: [
                ['SKU-A', 'B001', '9.99', '2', 'Active', 'DEFAULT'],
                ['SKU-B', 'B002', '4.50', '1', 'Inactive', 'DEFAULT'],
                ['SKU-MISSING', 'B003', '3.00', '1', 'Active', 'DEFAULT'],
            ],
        );

        $result = app(AmazonListingsReportImporter::class)->import($user, $report);

        $this->assertSame(2, $result['matched']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertContains('SKU-MISSING', $result['unmatched_skus']);

        $listingA = $itemA->listings()->first();
        $this->assertSame(ListingStatus::Active, $listingA->status);
        $this->assertSame('B001', $listingA->external_id);
        $this->assertSame('9.99', (string) $listingA->listed_price);
        $this->assertSame(2, $listingA->listed_quantity);
        $this->assertSame(InventoryStatus::Listed, $itemA->refresh()->status);

        $this->assertSame(ListingStatus::Inactive, $itemB->listings()->first()->status);

        // A (disconnected) Amazon account was created to host the imported listings.
        $this->assertSame(1, $user->marketplaceAccounts()->where('channel', 'amazon')->count());
    }

    public function test_report_without_status_column_treats_rows_as_active(): void
    {
        $user = User::factory()->create();
        $item = $this->itemFor($user, 'SKU-OPEN');

        $report = $this->tsv(
            header: ['seller-sku', 'price', 'quantity'],
            rows: [['SKU-OPEN', '7.00', '3']],
        );

        app(AmazonListingsReportImporter::class)->import($user, $report);

        $this->assertSame(ListingStatus::Active, $item->listings()->first()->status);
    }

    public function test_upload_endpoint_imports_and_reports_summary(): void
    {
        $user = User::factory()->create();
        $this->itemFor($user, 'SKU-A');

        $report = $this->tsv(
            header: ['seller-sku', 'asin1', 'price', 'quantity', 'status'],
            rows: [['SKU-A', 'B001', '9.99', '2', 'Active']],
        );
        $file = UploadedFile::fake()->createWithContent('AllListings.txt', $report);

        $this->actingAs($user)
            ->post(route('marketplace.amazon.import'), ['report' => $file])
            ->assertRedirect()
            ->assertSessionHas('status');
    }
}
