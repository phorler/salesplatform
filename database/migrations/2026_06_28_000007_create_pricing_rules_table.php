<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-seller pricing configuration: which strategy to use, the per-condition
 * multipliers applied to a reference/market price, and optional floor/ceiling and
 * undercut amount. One row per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('strategy')->default('competitive'); // competitive | manual_multiplier
            $table->json('multipliers')->nullable();            // {condition: multiplier}
            $table->decimal('price_floor', 10, 2)->nullable();
            $table->decimal('price_ceiling', 10, 2)->nullable();
            $table->decimal('undercut_amount', 10, 2)->nullable(); // sit this far below competition
            $table->string('currency', 3)->default('GBP');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
