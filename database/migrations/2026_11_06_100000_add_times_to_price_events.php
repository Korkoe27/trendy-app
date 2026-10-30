<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional time of day on a VIP price day: a start time on the first day and an end time on the
 * last day. Null means the edge of the session, which is how every existing event behaves, so
 * nothing already scheduled changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_events', function (Blueprint $table) {
            $table->time('starts_time')->nullable();
            $table->time('ends_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('price_events', function (Blueprint $table) {
            $table->dropColumn(['starts_time', 'ends_time']);
        });
    }
};
