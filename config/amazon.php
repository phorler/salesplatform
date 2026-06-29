<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Amazon SP-API
    |--------------------------------------------------------------------------
    |
    | One SP-API application authorises many sellers via OAuth. The LWA client
    | id/secret and the app id come from your Seller Central developer console.
    | Until those are set, the "Connect Amazon" flow shows a not-configured
    | notice rather than erroring.
    |
    | While the app is in draft (not yet published to the Appstore), keep
    | SPAPI_DRAFT_APP=true so the consent URL includes version=beta. Keep
    | SPAPI_SANDBOX=true to exercise the SP-API sandbox before live role approval.
    |
    */

    'app_id' => env('SPAPI_APP_ID'),

    'lwa' => [
        'client_id' => env('SPAPI_LWA_CLIENT_ID'),
        'client_secret' => env('SPAPI_LWA_CLIENT_SECRET'),
    ],

    // Marketplace code understood by SellingPartnerApi\Enums\Marketplace (GB, US, DE…).
    'marketplace' => env('AMAZON_MARKETPLACE', 'GB'),

    'sandbox' => (bool) env('SPAPI_SANDBOX', true),
    'draft_app' => (bool) env('SPAPI_DRAFT_APP', true),

    // Amazon product type used when creating book offers, and the fulfillment
    // channel code (DEFAULT = merchant-fulfilled). May need tuning per catalogue.
    'book_product_type' => env('AMAZON_BOOK_PRODUCT_TYPE', 'ABIS_BOOK'),
    'fulfillment_channel_code' => env('AMAZON_FULFILLMENT_CHANNEL', 'DEFAULT'),

];
