<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');

            // e.g. 'purchase', 'inventory', 'edit', 'correction', 'legacy_import'
            $table->string('type');

            // Signed change in units. Current stock = SUM(quantity_delta) for a product.
            $table->decimal('quantity_delta', 12, 2);

            // What produced this movement (e.g. 'stock' + the stocks.id it was recorded
            // alongside during the transition period, or an expense/daily_sales row later on).
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // The business date this movement applies to, distinct from created_at
            // (when the record was actually written).
            $table->timestamp('occurred_at');

            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->unique(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
