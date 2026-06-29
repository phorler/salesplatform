<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reconciled sale (order line) synced back from a marketplace. The unique
 * (channel, external_order_item_id) key makes order sync idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('external_order_id');
            $table->string('external_order_item_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('sale_price', 10, 2);            // unit price actually sold for
            $table->decimal('fees', 10, 2)->nullable();      // channel/referral fees
            $table->string('currency', 3)->default('GBP');
            $table->string('buyer_marketplace')->nullable();
            $table->timestamp('sold_at');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'external_order_item_id']);
            $table->index(['user_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
