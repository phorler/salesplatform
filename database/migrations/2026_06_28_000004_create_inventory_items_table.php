<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A physical copy a seller holds, in a given condition, ready to be listed.
 * User-scoped. SKU is unique per seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('condition');                       // App\Enums\Condition
            $table->string('condition_note')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('cost', 10, 2)->nullable();        // what the seller paid
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->decimal('list_price', 10, 2)->nullable();  // the price actually used to list
            $table->string('currency', 3)->default('GBP');
            $table->string('status')->default('draft');        // App\Enums\InventoryStatus
            $table->string('location')->nullable();            // shelf/bin reference
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sku']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
