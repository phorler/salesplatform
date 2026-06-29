<?php

namespace App\Console\Commands;

use App\Enums\MarketplaceAccountStatus;
use App\Models\MarketplaceAccount;
use App\Services\OrderSyncService;
use Illuminate\Console\Command;

class SyncOrders extends Command
{
    protected $signature = 'marketplace:sync-orders';

    protected $description = 'Pull orders from connected marketplaces and reconcile them into sales.';

    public function handle(OrderSyncService $orders): int
    {
        MarketplaceAccount::withoutGlobalScopes()
            ->where('status', MarketplaceAccountStatus::Connected)
            ->get()
            ->each(function (MarketplaceAccount $account) use ($orders) {
                try {
                    $count = $orders->sync($account);
                    $this->info("Account {$account->id} ({$account->channel}): {$count} new sale(s).");
                } catch (\Throwable $e) {
                    report($e);
                    $this->error("Account {$account->id}: {$e->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}
