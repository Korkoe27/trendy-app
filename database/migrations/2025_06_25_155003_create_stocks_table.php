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
            ->onDelete('cascade'); //grab product details

            $table->decimal('available_units' ,8,2)->default(0)->nullable(); // number of units sold that day

            $table->string('supplier')->nullable();



            $table->text('notes')->nullable();

            $table->decimal('cost_margin' ,8,2)->default(0)->nullable(); // number of boxes sold that day

            $table->decimal('cost_price' ,8,2)->default(0)->nullable(); // number of boxes sold that day
            
            $table->decimal('available_boxes' ,8,2)->default(0)->nullable(); // number of boxes sold that day
            // $table->foreignId('created_by')
            //     ->constrained('users')
            //     ->onDelete('cascade')
            //     ->nullable(); //user who created the stock entry

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
