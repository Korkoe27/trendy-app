<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Closing an order is limited to admins, managers and the waiter who took it. "Admins and
 * managers" are the roles holding order_debts -> modify (settle a tab on someone else's behalf),
 * so give that right, plus the ability to see the order board and the unpaid tabs, to the roles
 * named Super Admin, Admin and Manager. Rights are only ever switched on here, never off, and
 * every other role is left exactly as it is (the Users page can still change any of it).
 */
return new class extends Migration
{
    private const ROLES = ['Super Admin', 'Admin', 'Manager'];

    /** module => the permission columns to switch on */
    private const GRANTS = [
        'orders' => ['can_view'],
        'order_debts' => ['can_view', 'can_modify'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');

            foreach (self::GRANTS as $moduleName => $columns) {
                $moduleId = DB::table('modules')->where('name', $moduleName)->value('id');

                if (! $moduleId) {
                    continue;
                }

                foreach ($roleIds as $roleId) {
                    $grant = array_fill_keys($columns, true);

                    $exists = DB::table('permissions')->where('role_id', $roleId)->where('module_id', $moduleId)->exists();

                    if ($exists) {
                        DB::table('permissions')
                            ->where('role_id', $roleId)
                            ->where('module_id', $moduleId)
                            ->update($grant + ['updated_at' => now()]);

                        continue;
                    }

                    DB::table('permissions')->insert($grant + [
                        'role_id' => $roleId,
                        'module_id' => $moduleId,
                        'can_view' => false,
                        'can_create' => false,
                        'can_modify' => false,
                        'can_delete' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /** Rights are not switched back off: we cannot tell which were granted here and which by hand. */
    public function down(): void
    {
        //
    }
};
