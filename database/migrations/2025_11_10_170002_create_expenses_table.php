<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->timestamp('incurred_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('payment_method');
            $table->string('paid_by')->nullable();
            $table->string('category')->nullable(); 
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'paid', 'canceled'])->default('pending');
            $table->string('supplier')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
