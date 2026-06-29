<?php

use App\Services\Pricing\CompetitivePricingStrategy;
use App\Services\Pricing\KeepaPricingStrategy;
use App\Services\Pricing\ManualMultiplierStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Pricing strategies
    |--------------------------------------------------------------------------
    |
    | key => class implementing App\Services\Pricing\PricingStrategy. The
    | competitive (live Amazon) strategy is registered in Milestone 6. 'default'
    | is used when a seller's configured strategy isn't available.
    |
    */

    'default' => ManualMultiplierStrategy::KEY,

    'strategies' => [
        ManualMultiplierStrategy::KEY => ManualMultiplierStrategy::class,
        CompetitivePricingStrategy::KEY => CompetitivePricingStrategy::class,
        KeepaPricingStrategy::KEY => KeepaPricingStrategy::class,
    ],

];
