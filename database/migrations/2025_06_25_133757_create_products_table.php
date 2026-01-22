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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->onDelete('cascade');
            
            $table->string('sku')
            ->unique()
            ->nullable();


            $table->integer('stock_limit')->nullable();

            $table->string('barcode')->unique()->nullable();

            // $table->decimal('cost_price', 8, 2)->default(0.00)->nullable();

            $table->decimal('selling_price', 8, 2)->default(0.00);

            
            $table->json('metadata')->nullable();

            $table->integer('units_per_box')->default(0)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
