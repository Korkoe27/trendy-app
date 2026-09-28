<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->timestamp('occurred_at')->nullable();
        });

        // Backfill existing rows so historical movements keep a business date
        // instead of showing as unset.
        DB::table('stocks')->whereNull('occurred_at')->update([
            'occurred_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('occurred_at');
        });
    }
};
