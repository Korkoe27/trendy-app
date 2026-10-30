<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom prices for VIP price days: instead of one percentage, an event can carry an exact price
 * per product. A custom event has no percentage (percent IS NULL) and keeps each product's price
 * on the price_event_products row. Additive: one nullable column and one NOT NULL relaxed, so no
 * existing event changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_events', function (Blueprint $table) {
            $table->decimal('percent', 6, 2)->nullable()->change();
        });

        Schema::table('price_event_products', function (Blueprint $table) {
            $table->decimal('custom_price', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('price_event_products', function (Blueprint $table) {
            $table->dropColumn('custom_price');
        });
    }
};
