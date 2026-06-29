<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Seeded users start with no password and set it on first use.
            $table->string('password')->nullable()->change();
            $table->boolean('must_set_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_set_password');
            $table->string('password')->nullable(false)->change();
        });
    }
};
