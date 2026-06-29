<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An inventory item published (or being published) to one marketplace account.
 * Carries user_id directly for row-level scoping even though it's reachable via
 * the inventory item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_account_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('external_id')->nullable();     // ASIN (matched catalog item)
            $table->string('sku')->nullable();             // channel SKU used for the offer
            $table->string('submission_id')->nullable();   // async submission reference
            $table->string('status')->default('draft');    // App\Enums\ListingStatus
            $table->json('issues')->nullable();            // channel validation errors/warnings
            $table->decimal('listed_price', 10, 2)->nullable();
            $table->unsignedInteger('listed_quantity')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace_account_id', 'status']);
            $table->index(['channel', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
