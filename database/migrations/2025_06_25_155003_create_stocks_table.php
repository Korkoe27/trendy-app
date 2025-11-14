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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade'); // grab product details
            
            // $table->decimal('total_units', 8, 2)->default(0); // only store total units

            $table->integer('free_units')->nullable(); // free units in stock

            $table->integer('total_units')->default(0); // total units in stock
            
            $table->string('supplier')->nullable();
            
            $table->decimal('total_cost', 8, 2)->default(0); // total cost of all units purchased
            
            $table->decimal('cost_price', 8, 2)->default(0); // cost per unit (total_cost / units_per_box)
            
            $table->decimal('cost_margin', 8, 2)->default(0); // selling_price - cost_price
            
            $table->text('notes')->nullable();

            $table->timestamp('restock_date')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
