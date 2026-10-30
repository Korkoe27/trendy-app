<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ORD-0001 style, set from the id right after insert.
            $table->string('code')->nullable()->unique();

            $table->string('customer_name');
            $table->string('customer_phone', 20); // +233... , also used for mobile-money payment

            // pending (bartender to prepare) -> ready -> served (tab is open) -> closed | cancelled
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid'); // unpaid | paid
            $table->string('payment_method')->nullable();        // cash | momo
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();

            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('taken_by_name');

            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('served_at')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closed_by_name')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('payment_status');
            $table->index('customer_phone');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Name and price are snapshots, so later price changes never rewrite an old order.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);

            // pending (not yet prepared, still editable) | ready (locked)
            $table->string('status')->default('pending');
            $table->timestamp('ready_at')->nullable();

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('added_by_name')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
