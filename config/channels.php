<?php

use App\Channels\AmazonChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace channel drivers
    |--------------------------------------------------------------------------
    |
    | Map of channel key => class implementing App\Channels\Contracts\
    | MarketplaceChannel. Resolved lazily by ChannelManager. Add eBay/Facebook
    | here once their adapters exist. AmazonChannel is wired in Milestone 6.
    |
    */

    'drivers' => [
        'amazon' => AmazonChannel::class,
    ],

];
