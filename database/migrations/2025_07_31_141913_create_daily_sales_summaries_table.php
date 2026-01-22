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
            $table->float('drinks_total');

            $table->integer('items_sold')->default(0);

            $table->float('total_cash')->default(0.00);

            $table->float('total_momo')->default(0.00);

            $table->float('total_hubtel')->default(0.00);

            $table->float('on_the_house')->default(0.00);
            
            $table->float('snooker')->default(0.00);

            $table->float('first_extra')->default(0.00)->nullable();
            
            $table->float('second_extra')->default(0.00)->nullable();

            $table->float('third_extra')->default(0.00)->nullable();
            
            $table->float('food_total')->default(0.00);
            $table->float('total_money')->default(0.00);
            $table->float('total_loss_amount')->default(0.00);
            $table->float('total_credit_amount')->default(0.00);
            $table->float('total_credit_units')->default(0.00);
            $table->float('total_damaged')->default(0.00);

            // most sold
            $table->float('total_profit')->default(0.00);

            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');

            $table->json('metadata')->nullable();

            $table->date('sales_date')->nullable()->after('id');
            $table->index('sales_date');

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
