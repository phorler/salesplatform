<?php

namespace App\Http\Controllers;

use App\Enums\MarketplaceAccountStatus;
use App\Services\Amazon\AmazonOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SellingPartnerApi\Enums\Marketplace;

/**
 * Per-seller Amazon authorization (OAuth). connect() sends the seller to Seller
 * Central to grant access; callback() exchanges the returned code for a refresh
 * token and stores it (encrypted) on a MarketplaceAccount.
 */
class AmazonAuthController extends Controller
{
    public function __construct(private readonly AmazonOAuth $oauth) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()
                ->route('marketplace.index')
                ->withErrors(['amazon' => 'Amazon integration is not configured yet (missing SP-API credentials).']);
        }

        $state = Str::random(40);
        $request->session()->put('amazon_oauth_state', $state);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('amazon_oauth_state');

        if ($request->filled('error') || ! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect()
                ->route('marketplace.index')
                ->withErrors(['amazon' => 'Amazon authorization was cancelled or could not be verified.']);
        }

        $code = (string) $request->query('spapi_oauth_code');
        if ($code === '') {
            return redirect()
                ->route('marketplace.index')
                ->withErrors(['amazon' => 'Amazon did not return an authorization code.']);
        }

        try {
            $refreshToken = $this->oauth->exchangeCodeForRefreshToken($code);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('marketplace.index')
                ->withErrors(['amazon' => 'Could not complete the Amazon connection. Please try again.']);
        }

        $marketplace = $this->oauth->marketplace();

        $request->user()->marketplaceAccounts()->updateOrCreate(
            ['channel' => 'amazon', 'marketplace_id' => $marketplace->value],
            [
                'label' => 'Amazon '.$marketplace->name,
                'region' => strtolower(Marketplace::toRegion($marketplace)->name),
                'selling_partner_id' => $request->query('selling_partner_id'),
                'refresh_token' => $refreshToken,
                'status' => MarketplaceAccountStatus::Connected,
            ],
        );

        return redirect()
            ->route('marketplace.index')
            ->with('status', 'Amazon connected successfully.');
    }
}
