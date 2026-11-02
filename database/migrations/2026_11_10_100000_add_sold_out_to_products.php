<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * During service the bar can switch a product off when it runs out (orders never change stock, so
 * the stock figure cannot tell). NULL = available, which is every existing product. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('sold_out_at')->nullable();
            $table->string('sold_out_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sold_out_at', 'sold_out_by_name']);
        });
    }
};
