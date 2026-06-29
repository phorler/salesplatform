<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seller's connection to one marketplace (e.g. Amazon UK). Holds the per-seller
 * OAuth refresh token (encrypted at the model layer) used to call the channel API
 * on their behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');                       // 'amazon', later 'ebay', ...
            $table->string('label')->nullable();             // human label, e.g. "Amazon UK"
            $table->string('region')->nullable();            // 'eu', 'na', ...
            $table->string('marketplace_id')->nullable();    // e.g. A1F83G8C2ARO7P (amazon.co.uk)
            $table->string('selling_partner_id')->nullable();
            $table->text('refresh_token')->nullable();       // encrypted cast on the model
            $table->json('credentials')->nullable();         // any extra channel-specific config
            $table->string('status')->default('disconnected');
            $table->timestamp('orders_synced_at')->nullable(); // watermark for incremental order sync
            $table->timestamps();

            $table->unique(['user_id', 'channel', 'marketplace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_accounts');
    }
};
