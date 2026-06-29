<?php

namespace App\Channels;

use App\Channels\Contracts\MarketplaceChannel;
use App\Channels\Exceptions\UnknownChannelException;
use App\Models\MarketplaceAccount;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a concrete MarketplaceChannel by key. Channels are registered in
 * config/channels.php as key => class-string and built through the container,
 * so adding a new marketplace is a one-line config change.
 */
class ChannelManager
{
    /** @var array<string, MarketplaceChannel> */
    protected array $resolved = [];

    public function __construct(
        protected Container $container,
        /** @var array<string, class-string<MarketplaceChannel>> */
        protected array $drivers = [],
    ) {}

    public function driver(string $key): MarketplaceChannel
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if (! isset($this->drivers[$key])) {
            throw UnknownChannelException::for($key);
        }

        return $this->resolved[$key] = $this->container->make($this->drivers[$key]);
    }

    public function for(MarketplaceAccount $account): MarketplaceChannel
    {
        return $this->driver($account->channel);
    }

    /** @return array<int, string> */
    public function available(): array
    {
        return array_keys($this->drivers);
    }

    public function has(string $key): bool
    {
        return isset($this->drivers[$key]);
    }
}
