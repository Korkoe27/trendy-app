<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per business day whose coin requests were closed by hand at the end of the night. It holds
 * who closed it and a snapshot of what was given at that moment; the day-by-day list on the Coin
 * Requests page is otherwise computed from coin_requests. Additive: a new table, nothing else changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_days', function (Blueprint $table) {
            $table->id();

            $table->date('business_date')->unique();

            // NULL = not closed (never closed, or reopened).
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closed_by_name')->nullable();

            // What had been given when the night was closed, and how many waiting requests were cancelled by closing.
            $table->unsignedInteger('closed_coins')->nullable();
            $table->decimal('closed_amount', 10, 2)->nullable();
            $table->unsignedInteger('closed_cancelled')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_days');
    }
};
