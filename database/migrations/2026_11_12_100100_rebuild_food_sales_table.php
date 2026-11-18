<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * food_sales becomes the daily summary of food orders (one row per business day), written when the
 * end-of-day stock count is submitted. The old food_sales table (product, user, quantity) was never
 * used by the app. If it holds rows they are kept by renaming it to food_sales_legacy; an empty
 * one is simply dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('food_sales')) {
            if (DB::table('food_sales')->exists()) {
                Schema::rename('food_sales', 'food_sales_legacy');
            } else {
                Schema::drop('food_sales');
            }
        }

        Schema::create('food_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sales_date')->unique();

            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('items_count')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);

            // Each dish sold that day: name, quantity, amount.
            $table->json('breakdown')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_sales');

        if (Schema::hasTable('food_sales_legacy')) {
            Schema::rename('food_sales_legacy', 'food_sales');
        }
    }
};
