<?php

namespace Tests\Feature;

use App\Channels\AmazonChannel;
use App\Channels\ChannelManager;
use App\Enums\MarketplaceAccountStatus;
use App\Models\MarketplaceAccount;
use App\Models\User;
use App\Services\Amazon\AmazonOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SellingPartnerApi\Enums\Marketplace;
use Tests\TestCase;

class AmazonConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_manager_resolves_amazon(): void
    {
        $manager = app(ChannelManager::class);

        $this->assertTrue($manager->has('amazon'));
        $this->assertInstanceOf(AmazonChannel::class, $manager->driver('amazon'));
        $this->assertSame('amazon', $manager->driver('amazon')->key());
    }

    public function test_marketplace_index_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('marketplace.index'))
            ->assertOk()
            ->assertSee('Connect Amazon');
    }

    public function test_connect_redirects_to_amazon_when_configured(): void
    {
        $this->mock(AmazonOAuth::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('authorizationUrl')->andReturn('https://sellercentral.amazon.co.uk/apps/authorize/consent?x=1');
        });

        $this->actingAs(User::factory()->create())
            ->get(route('marketplace.amazon.connect'))
            ->assertRedirect('https://sellercentral.amazon.co.uk/apps/authorize/consent?x=1')
            ->assertSessionHas('amazon_oauth_state');
    }

    public function test_connect_shows_error_when_not_configured(): void
    {
        $this->mock(AmazonOAuth::class, fn ($mock) => $mock->shouldReceive('isConfigured')->andReturnFalse());

        $this->actingAs(User::factory()->create())
            ->get(route('marketplace.amazon.connect'))
            ->assertRedirect(route('marketplace.index'))
            ->assertSessionHasErrors('amazon');
    }

    public function test_callback_stores_encrypted_refresh_token(): void
    {
        $this->mock(AmazonOAuth::class, function ($mock) {
            $mock->shouldReceive('marketplace')->andReturn(Marketplace::GB);
            $mock->shouldReceive('exchangeCodeForRefreshToken')->with('the-code')->andReturn('Atzr|FAKEREFRESH');
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['amazon_oauth_state' => 'st4te'])
            ->get(route('marketplace.amazon.callback', [
                'state' => 'st4te',
                'spapi_oauth_code' => 'the-code',
                'selling_partner_id' => 'A1SELLER',
            ]))
            ->assertRedirect(route('marketplace.index'))
            ->assertSessionHas('status');

        $account = $user->marketplaceAccounts()->firstOrFail();
        $this->assertSame('amazon', $account->channel);
        $this->assertSame(Marketplace::GB->value, $account->marketplace_id);
        $this->assertSame('A1SELLER', $account->selling_partner_id);
        $this->assertSame(MarketplaceAccountStatus::Connected, $account->status);
        $this->assertSame('Atzr|FAKEREFRESH', $account->refresh_token); // decrypts via cast

        // Stored encrypted, not as plaintext.
        $this->assertDatabaseMissing('marketplace_accounts', ['refresh_token' => 'Atzr|FAKEREFRESH']);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['amazon_oauth_state' => 'expected'])
            ->get(route('marketplace.amazon.callback', ['state' => 'forged', 'spapi_oauth_code' => 'x']))
            ->assertRedirect(route('marketplace.index'))
            ->assertSessionHasErrors('amazon');

        $this->assertSame(0, $user->marketplaceAccounts()->count());
    }

    public function test_owner_can_disconnect(): void
    {
        $user = User::factory()->create();
        $account = MarketplaceAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('marketplace.destroy', $account))
            ->assertRedirect(route('marketplace.index'));

        $this->assertDatabaseMissing('marketplace_accounts', ['id' => $account->id]);
    }
}
