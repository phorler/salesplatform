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
