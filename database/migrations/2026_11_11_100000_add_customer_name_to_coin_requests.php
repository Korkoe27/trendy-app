<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name of the player a snooker coin is for, typed by the attendant. Additive: one nullable
 * column, so every request already recorded stays as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_requests', function (Blueprint $table) {
            $table->string('customer_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coin_requests', function (Blueprint $table) {
            $table->dropColumn('customer_name');
        });
    }
};
