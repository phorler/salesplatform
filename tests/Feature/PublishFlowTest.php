<?php

namespace Tests\Feature;

use App\Channels\ChannelManager;
use App\Channels\Data\SubmissionResult;
use App\Enums\InventoryStatus;
use App\Enums\ListingStatus;
use App\Jobs\PollListingStatusJob;
use App\Jobs\PublishListingJob;
use App\Models\InventoryItem;
use App\Models\Listing;
use App\Models\MarketplaceAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\ListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeChannel;
use Tests\Support\UsesFakeChannel;
use Tests\TestCase;

class PublishFlowTest extends TestCase
{
    use RefreshDatabase;
    use UsesFakeChannel;

    private function makeFixtures(): array
    {
        $user = User::factory()->create();
        $account = MarketplaceAccount::factory()->create(['user_id' => $user->id]);
        $item = InventoryItem::factory()->create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'list_price' => 7.50,
            'quantity' => 2,
        ]);

        return [$user, $account, $item];
    }

    public function test_queue_publish_creates_pending_listing_and_dispatches_job(): void
    {
        Queue::fake();
        [$user, $account, $item] = $this->makeFixtures();
        $this->actingAs($user);

        $listing = app(ListingService::class)->queuePublish($item, $account);

        $this->assertSame(ListingStatus::Pending, $listing->refresh()->status);
        Queue::assertPushed(PublishListingJob::class, fn ($job) => $job->listingId === $listing->id);
    }

    public function test_publish_job_marks_item_listed_when_active(): void
    {
        $this->bindFakeChannel((function () {
            $f = new FakeChannel;
            $f->submission = new SubmissionResult(ListingStatus::Active, externalId: 'B0ACTIVE');

            return $f;
        })());

        [$user, $account, $item] = $this->makeFixtures();
        $listing = Listing::create([
            'user_id' => $user->id, 'inventory_item_id' => $item->id,
            'marketplace_account_id' => $account->id, 'channel' => 'amazon',
            'sku' => $item->sku, 'status' => ListingStatus::Pending,
        ]);

        (new PublishListingJob($listing->id))->handle(app(ChannelManager::class));

        $this->assertSame(ListingStatus::Active, $listing->refresh()->status);
        $this->assertSame('B0ACTIVE', $listing->external_id);
        $this->assertSame(InventoryStatus::Listed, $item->refresh()->status);
    }

    public function test_publish_job_queues_status_poll_when_pending(): void
    {
        Queue::fake();
        $this->bindFakeChannel((function () {
            $f = new FakeChannel;
            $f->submission = new SubmissionResult(ListingStatus::Pending, submissionId: 'SUB1');

            return $f;
        })());

        [$user, $account, $item] = $this->makeFixtures();
        $listing = Listing::create([
            'user_id' => $user->id, 'inventory_item_id' => $item->id,
            'marketplace_account_id' => $account->id, 'channel' => 'amazon',
            'sku' => $item->sku, 'status' => ListingStatus::Pending,
        ]);

        (new PublishListingJob($listing->id))->handle(app(ChannelManager::class));

        $this->assertSame('SUB1', $listing->refresh()->submission_id);
        Queue::assertPushed(PollListingStatusJob::class);
    }

    public function test_publish_endpoint_requires_connected_account(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create(['user_id' => $user->id, 'product_id' => Product::factory()->create()->id]);

        $this->actingAs($user)
            ->post(route('listings.publish', $item))
            ->assertSessionHasErrors('publish');
    }

    public function test_publish_endpoint_queues_job(): void
    {
        Queue::fake();
        [$user, $account, $item] = $this->makeFixtures();

        $this->actingAs($user)
            ->post(route('listings.publish', $item))
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(PublishListingJob::class);
    }
}
