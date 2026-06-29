<?php

use App\Http\Controllers\AmazonAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MarketplaceAccountController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // No marketing splash — send people straight into the app (login if guest).
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Inventory + ISBN add flow
    Route::get('/inventory', [InventoryItemController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [InventoryItemController::class, 'create'])->name('inventory.create');
    Route::post('/inventory/lookup', [InventoryItemController::class, 'lookup'])->name('inventory.lookup');
    Route::post('/inventory', [InventoryItemController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{inventoryItem}', [InventoryItemController::class, 'show'])->name('inventory.show');
    Route::get('/inventory/{inventoryItem}/edit', [InventoryItemController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventoryItem}', [InventoryItemController::class, 'destroy'])->name('inventory.destroy');

    // Publish to a marketplace + live pricing
    Route::post('/inventory/{inventoryItem}/publish', [ListingController::class, 'store'])->name('listings.publish');
    Route::post('/inventory/{inventoryItem}/refresh-price', [ListingController::class, 'refreshPrice'])->name('listings.refresh-price');

    // Sales history
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');

    // Settings
    Route::get('/settings/pricing', [PricingRuleController::class, 'edit'])->name('settings.pricing.edit');
    Route::put('/settings/pricing', [PricingRuleController::class, 'update'])->name('settings.pricing.update');

    // Marketplace accounts + Amazon OAuth
    Route::get('/marketplace', [MarketplaceAccountController::class, 'index'])->name('marketplace.index');
    Route::delete('/marketplace/{marketplaceAccount}', [MarketplaceAccountController::class, 'destroy'])->name('marketplace.destroy');
    Route::get('/marketplace/amazon/connect', [AmazonAuthController::class, 'connect'])->name('marketplace.amazon.connect');
    Route::get('/marketplace/amazon/callback', [AmazonAuthController::class, 'callback'])->name('marketplace.amazon.callback');
});

require __DIR__.'/auth.php';
