<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The many-to-many catalog: which products a supplier sells and at what price.
     * `stocks` remains the purchase ledger; this table is the reusable price list
     * that the "add stock" flow looks up to auto-calculate cost.
     */
    public function up(): void
    {
        Schema::create('supplier_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->decimal('unit_cost', 12, 2);           // supplier's current price per unit
            $table->string('supplier_sku')->nullable();     // their reference for this product
            $table->integer('units_per_box')->nullable();   // supplier-specific pack size
            $table->integer('min_order_qty')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false); // cheapest / default supplier for product
            $table->boolean('is_active')->default(true);

            // Denormalised from purchase history for quick "last bought" answers.
            $table->decimal('last_purchase_price', 12, 2)->nullable();
            $table->timestamp('last_purchased_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
            $table->index(['product_id', 'unit_cost']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product');
    }
};
