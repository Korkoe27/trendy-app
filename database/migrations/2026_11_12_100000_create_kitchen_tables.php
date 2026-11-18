<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kitchen: food orders (the food part of an order, tracked on the kitchen dashboard), the
 * foodstuff the kitchen buys, and the chef's shopping lists. Additive: new tables and three
 * columns on order_items (every existing item stays a bar item).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->date('business_date');

            $table->string('customer_name');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('taken_by_name')->nullable();

            // pending -> ready (chef) -> picked_up (the waiter); cancelled with the order.
            $table->string('status', 20)->default('pending');
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedInteger('items_count')->default(0);

            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_up_at')->nullable();
            $table->foreignId('picked_up_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['business_date', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Who prepares the item: the bar or the kitchen.
            $table->string('station', 20)->default('bar');
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('food_order_id')->nullable()->constrained('food_orders')->nullOnDelete();
        });

        Schema::create('foodstuff', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('unit', 30)->nullable();
            $table->string('supplier')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();

            // pending (to buy) -> bought, or cancelled.
            $table->string('status', 20)->default('pending');
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();

            $table->timestamp('bought_at')->nullable();
            $table->foreignId('bought_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bought_by_name')->nullable();
            $table->string('payment_method')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained('shopping_lists')->cascadeOnDelete();
            $table->foreignId('foodstuff_id')->nullable()->constrained('foodstuff')->nullOnDelete();

            $table->string('item_name');
            $table->string('unit', 30)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('cost', 10, 2); // price of one unit
            $table->decimal('line_total', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
        Schema::dropIfExists('foodstuff');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_order_id');
            $table->dropConstrainedForeignId('ready_by');
            $table->dropColumn('station');
        });

        Schema::dropIfExists('food_orders');
    }
};
