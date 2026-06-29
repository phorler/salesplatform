<?php

namespace App\Providers;

use App\Channels\ChannelManager;
use Illuminate\Support\ServiceProvider;

class ChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class, function ($app) {
            return new ChannelManager(
                $app,
                config('channels.drivers', []),
            );
        });
    }
}
