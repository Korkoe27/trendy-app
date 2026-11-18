<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The bartender hands coins over but must never void them: voiding reverses money already
     * recorded for a given request and is a manager's call. The coin_requests migration only
     * grants the Bartender view + modify, so this also repairs any database where the delete
     * right was switched on by hand on the Users page.
     */
    private const MODULE = 'coin_requests';

    private const ROLE = 'Bartender';

    public function up(): void
    {
        DB::transaction(function () {
            $roleId = DB::table('roles')->where('name', self::ROLE)->value('id');
            $moduleId = DB::table('modules')->where('name', self::MODULE)->value('id');

            if (! $roleId || ! $moduleId) {
                return;
            }

            DB::table('permissions')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->update(['can_delete' => false, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Deliberately left as a no-op: re-granting void rights is a decision for the
        // Users page, not something a rollback should switch on by itself.
    }
};
