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
        Schema::create('daily_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->float('total_revenue');
            $table->integer('items_sold')->default(0);
            $table->float('total_cash')->default(0.00);
            $table->float('total_momo')->default(0.00);
            $table->float('total_hubtel')->default(0.00);
            $table->float('total_money')->default(0.00);
            $table->float('total_loss_amount')->default(0.00);
            $table->float('total_credit_amount')->default(0.00);
            $table->float('total_credit_units')->default(0.00);
            $table->float('total_damaged')->default(0.00);

            //most sold
            $table->float('total_profit')->default(0.00);
            
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};
