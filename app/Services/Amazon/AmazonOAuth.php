<?php

namespace App\Services\Amazon;

use SellingPartnerApi\Enums\Marketplace;
use SellingPartnerApi\OAuth;

/**
 * Thin wrapper around jlevers' OAuth helper for the per-seller authorization
 * flow: build the Seller Central consent URL, and exchange the returned
 * authorization code for a long-lived refresh token. Wrapping it keeps the
 * controller testable (this service is mocked in tests).
 */
class AmazonOAuth
{
    public function isConfigured(): bool
    {
        return (bool) (config('amazon.app_id')
            && config('amazon.lwa.client_id')
            && config('amazon.lwa.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        return $this->oauth()->getAuthorizationUri(
            config('amazon.app_id'),
            $state,
            $this->marketplace(),
            (bool) config('amazon.draft_app'),
        );
    }

    public function exchangeCodeForRefreshToken(string $authCode): string
    {
        return $this->oauth()->getRefreshToken($authCode);
    }

    public function marketplace(): Marketplace
    {
        return Marketplace::fromCountryCode(config('amazon.marketplace', 'GB'));
    }

    /** The redirect URI must be https and match the SP-API app configuration. */
    public function redirectUri(): string
    {
        return route('marketplace.amazon.callback');
    }

    protected function oauth(): OAuth
    {
        return new OAuth(
            config('amazon.lwa.client_id'),
            config('amazon.lwa.client_secret'),
            $this->redirectUri(),
        );
    }
}
