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
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_sale_id')
                ->constrained('daily_sales')
                ->onDelete('cascade');
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->float('units_credited');
            $table->float('credit_amount');
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->date('credit_date');
            $table->date('payment_date')->nullable();
            $table->json('metadata')->nullable();
            $table->float('amount_paid')->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();


            $table->index(['status', 'credit_date']);
            $table->index('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_sales');
    }
};
