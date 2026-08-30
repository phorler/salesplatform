<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved Amazon CSV export: the exact file that was downloaded plus the set of
 * inventory items it covered, so it can be re-downloaded and the items marked
 * listed after they've been uploaded to Amazon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->json('item_ids');            // inventory_items covered by this export
            $table->unsignedInteger('item_count');
            $table->longText('csv');             // the exact file content, for re-download
            $table->timestamp('marked_listed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_batches');
    }
};
