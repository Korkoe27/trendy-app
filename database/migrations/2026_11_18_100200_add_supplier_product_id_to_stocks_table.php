<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace each purchase back to the supplier_product catalog row that priced it.
     * Nullable: legacy rows and purchases made before the catalog existed stay valid.
     */
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('supplier_product_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('supplier_product')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_product_id');
        });
    }
};
