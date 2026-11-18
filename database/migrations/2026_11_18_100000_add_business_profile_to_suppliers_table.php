<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enrich suppliers with a business profile used for statistics and analysis.
     * All fields are additive and nullable so existing rows are unaffected.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_person')->nullable();
            $table->string('alt_phone')->nullable();
            $table->string('region')->nullable();
            $table->string('supplier_type')->nullable(); // distributor | wholesaler | manufacturer | importer
            $table->string('tax_id')->nullable();
            $table->string('registration_number')->nullable();

            $table->integer('payment_terms_days')->nullable(); // credit days
            $table->integer('lead_time_days')->nullable();     // default delivery lead time

            $table->decimal('rating', 2, 1)->nullable();          // 0.0 - 5.0
            $table->unsignedSmallInteger('reliability_score')->nullable(); // 0 - 100

            $table->string('status')->default('active'); // active | preferred | inactive | blacklisted

            // Denormalised profile summary, maintained by the stock flow.
            $table->timestamp('first_purchase_at')->nullable();
            $table->timestamp('last_purchase_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'contact_person',
                'alt_phone',
                'region',
                'supplier_type',
                'tax_id',
                'registration_number',
                'payment_terms_days',
                'lead_time_days',
                'rating',
                'reliability_score',
                'status',
                'first_purchase_at',
                'last_purchase_at',
            ]);
        });
    }
};
