<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per mobile-money attempt for an order. An order is only marked paid when one of
     * these rows reaches "paid" (confirmed by the gateway), never because the browser said so.
     */
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Our idempotency key, sent to the gateway as the client reference.
            $table->string('client_reference', 40)->unique();
            $table->string('provider_reference')->nullable();

            $table->string('phone', 20);
            $table->string('channel', 30)->nullable(); // e.g. mtn-gh, vodafone-gh, tigo-gh
            $table->decimal('amount', 12, 2);          // always the order total at the moment of the attempt

            // initiated -> pending -> paid | failed | expired | cancelled
            $table->string('status')->default('initiated');
            $table->string('failure_reason')->nullable();

            // Gateway payloads kept for support and audit. Credentials are never stored here.
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('callback_payload')->nullable();

            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('initiated_by_name')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
