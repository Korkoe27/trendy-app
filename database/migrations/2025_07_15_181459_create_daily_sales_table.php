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
        Schema::create('daily_sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');

            $table->foreignId('stock_id')
                ->constrained('stocks')
                ->onDelete('cascade');

            $table->float('damaged_units')->nullable()->default(0.00)->after('closing_boxes');

            $table->float('credit_units')->nullable()->default(0.00)->after('damaged_units');

            $table->float('loss_amount')->nullable()->default(0.00)->after('damaged_units');

            $table->float('credit_amount')->nullable()->default(0.00)->after('credit_units');

            $table->float('opening_stock')->nullable()->default(0.00);

            $table->float('unit_profit')->nullable()->default(0.00);

            $table->float('closing_stock')->nullable()->default(0.00);

            $table->float('total_amount')->default(0.00);

            $table->float('opening_boxes')->nullable()->default(0.00);

            $table->float('closing_boxes')->nullable()->default(0.00);

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
        Schema::dropIfExists('daily_sales');
    }
};
