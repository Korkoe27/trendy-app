<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();

            // First day of the month whose debt this payment reduces.
            $table->date('period_month');

            $table->string('payer_name');
            $table->string('payer_phone')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('payment_method'); // cash | momo | hubtel
            $table->date('paid_at');
            $table->text('notes')->nullable();

            // Staff member who received the money (name is a snapshot).
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('received_by_name');

            // Snapshots of the month's outstanding debt around this payment, for audit.
            $table->decimal('outstanding_before', 12, 2);
            $table->decimal('outstanding_after', 12, 2);

            // Mistakes are voided, never deleted.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->index('period_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
