<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snooker (and other games) have no stock: a game product carries a number of coins per day that
 * come back every night. Coins taken from the bar are recorded as coin requests, and the end-of-day
 * count checks the snooker money against the coins given. Additive: a new table, one nullable
 * column on products and two nullable columns on the daily summaries (NULL = no snooker check, so
 * every existing day is unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Coins available per business day for a game product (null for everything else).
            $table->unsignedInteger('daily_coins')->nullable();
        });

        Schema::create('coin_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');

            // requested -> given, or cancelled (before it was given) / voided (after).
            $table->string('status', 20)->default('requested');

            // The session (business day) the coins were requested in.
            $table->date('business_date');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name')->nullable();
            $table->timestamp('requested_at')->useCurrent();

            // Captured when the bar hands the coins over.
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('given_by_name')->nullable();
            $table->timestamp('given_at')->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();

            $table->string('note')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->timestamps();

            $table->index(['business_date', 'status']);
            $table->index(['product_id', 'business_date']);
        });

        Schema::table('daily_sales_summaries', function (Blueprint $table) {
            // Coins given that day and what they should have brought in (a snapshot). NULL = not checked.
            $table->unsignedInteger('snooker_coins')->nullable();
            $table->decimal('snooker_expected', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_sales_summaries', function (Blueprint $table) {
            $table->dropColumn(['snooker_coins', 'snooker_expected']);
        });

        Schema::dropIfExists('coin_requests');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('daily_coins');
        });
    }
};
