<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global catalog cache, keyed by ISBN-13. Shared across all sellers so a book's
 * metadata (from Open Library) is fetched once and reused. Not user-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('isbn13', 13)->unique();
            $table->string('isbn10', 10)->nullable()->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->json('authors')->nullable();        // ["Name", ...]
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('cover_url')->nullable();
            $table->json('payload')->nullable();         // raw Open Library response
            $table->timestamp('fetched_at')->nullable(); // when metadata was last refreshed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
