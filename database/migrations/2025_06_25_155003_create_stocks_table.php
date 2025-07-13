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
            $table->integer('opening_units')->default(0);   //number of bottles available at the start of day

            $table->integer('opening_boxes')->default(0); //number of boxes available at the start of day

            $table->integer('added_units')->default(0)->nullable(); //number of units added during the day

            $table->integer('closing_units')->default(0); //number of units remaining at the end of day

            $table->decimal('closing_boxes' ,8,2)->default(0); //number of boxes remaining at the end of day

            $table->decimal('sales_units' ,8,2)->default(0); // number of units sold that day

            $table->decimal('sales_boxes' ,8,2)->default(0); // number of boxes sold that day
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
