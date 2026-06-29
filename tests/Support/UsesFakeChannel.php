<?php

namespace Tests\Support;

use App\Channels\ChannelManager;
use App\Services\Pricing\PricingService;

trait UsesFakeChannel
{
    protected function bindFakeChannel(?FakeChannel $fake = null): FakeChannel
    {
        $fake ??= new FakeChannel;

        $this->app->instance(FakeChannel::class, $fake);
        config()->set('channels.drivers.amazon', FakeChannel::class);

        // Rebuild singletons that captured the channel config/manager.
        $this->app->forgetInstance(ChannelManager::class);
        $this->app->forgetInstance(PricingService::class);

        return $fake;
    }
}
