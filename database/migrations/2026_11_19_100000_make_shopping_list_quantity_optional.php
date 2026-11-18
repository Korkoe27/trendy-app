<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every shopping list item is priced per unit: a line can be one price for the item with no
 * quantity. A blank quantity means "the price is for the item as a whole". Existing rows keep their quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(1)->nullable(false)->change();
        });
    }
};
