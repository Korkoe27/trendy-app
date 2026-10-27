<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', 'debt_payments')->value('id');

            if (! $moduleId) {
                $moduleId = DB::table('modules')->insertGetId([
                    'name' => 'debt_payments',
                    'description' => 'Debt Payments',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Super Admin gets full access; every other role starts with none and can be
            // granted access from the Users page.
            $superAdminId = DB::table('roles')->where('name', 'Super Admin')->value('id');

            foreach (DB::table('roles')->pluck('id') as $roleId) {
                $isSuperAdmin = $roleId === $superAdminId;

                $exists = DB::table('permissions')
                    ->where('role_id', $roleId)
                    ->where('module_id', $moduleId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('permissions')->insert([
                    'role_id' => $roleId,
                    'module_id' => $moduleId,
                    'can_create' => $isSuperAdmin,
                    'can_view' => $isSuperAdmin,
                    'can_modify' => $isSuperAdmin,
                    'can_delete' => $isSuperAdmin,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', 'debt_payments')->value('id');

            if ($moduleId) {
                DB::table('permissions')->where('module_id', $moduleId)->delete();
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        });
    }
};
