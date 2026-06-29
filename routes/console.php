<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcile marketplace orders into sales every 15 minutes (scheduler container).
Schedule::command('marketplace:sync-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Snapshot Amazon market prices (via Keepa) for in-stock books daily. Self-skips
// when Keepa isn't configured. Token-conservative; raise frequency if needed.
Schedule::command('keepa:refresh-prices')
    ->dailyAt('06:00')
    ->withoutOverlapping();
