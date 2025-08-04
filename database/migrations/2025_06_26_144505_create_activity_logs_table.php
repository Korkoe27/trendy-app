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
        Schema::create('activity_logs', function (Blueprint $table) {
            
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('action_type'); // e.g., 'login', 'logout', 'purchase', etc.
            $table->text('description')->nullable(); // Optional description of the action

            $table->string('entity_type'); //e.g. 'product', 'stock_record', 'coin_sale'

            $table->string('entity_id')->nullable(); // ID of the entity related to the action, if applicable

            $table->json('metadata')->nullable(); // Additional metadata related to the action

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
