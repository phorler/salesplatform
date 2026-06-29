<?php

namespace App\Providers;

use App\Services\Pricing\PricingService;
use Illuminate\Support\ServiceProvider;

class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PricingService::class, function ($app) {
            $strategies = [];
            foreach (config('pricing.strategies', []) as $key => $class) {
                $strategies[$key] = $app->make($class);
            }

            return new PricingService($strategies, config('pricing.default'));
        });
    }
}
