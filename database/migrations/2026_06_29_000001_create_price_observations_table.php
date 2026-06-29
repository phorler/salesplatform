<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Market price history per book (from Keepa). Global, keyed by product — the
 * market is the same regardless of which seller holds a copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('keepa');
            $table->string('asin')->nullable();
            $table->decimal('new_price', 10, 2)->nullable();
            $table->decimal('used_price', 10, 2)->nullable();
            $table->unsignedInteger('sales_rank')->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['product_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_observations');
    }
};
