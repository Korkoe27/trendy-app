<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The block list: customer phone numbers that may not place orders. Additive (one new table), so
 * nothing existing changes. Numbers are stored normalised (+233...), like every other phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->string('reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('blocked_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_numbers');
    }
};
