<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VIP price days: a product flag (the VIP list), the scheduled events, and the snapshot of which
 * products each event covers. products.selling_price is never touched: the price for a day is
 * worked out from these tables, so prices return to normal on their own after the last day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false);
        });

        Schema::create('price_events', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();

            // Inclusive business days (a one-day event has the same start and end).
            $table->date('starts_on');
            $table->date('ends_on');

            // +20 = twenty percent more, -10 = ten percent off.
            $table->decimal('percent', 6, 2);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Cancelled events are kept for the record and never match a price lookup.
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        // The VIP list as it was when the event was scheduled.
        Schema::create('price_event_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_event_id')->constrained('price_events')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->unique(['price_event_id', 'product_id']);
        });

        // Which event set the price of an order line (audit only; unit_price stays the real snapshot).
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('price_event_id')->nullable()->constrained('price_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_event_id');
        });

        Schema::dropIfExists('price_event_products');
        Schema::dropIfExists('price_events');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_vip');
        });
    }
};
