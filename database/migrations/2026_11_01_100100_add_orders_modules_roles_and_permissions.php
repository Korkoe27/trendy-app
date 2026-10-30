<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission map for the order system (module x action):
     *   orders       view   = see the order board
     *                create = "can_order": take orders, add items, serve, and take payment
     *                modify = mark orders ready (bartender), edit pending items
     *                delete = cancel an order
     *   order_debts  view   = "can_view_debts": see unpaid tabs and the dashboard card
     *                modify = settle/close an unpaid tab on someone else's behalf (manager)
     *
     * Super Admin gets everything. Two starter roles are created if they do not exist yet.
     * Every other existing role starts with no access and can be granted it on the Users page.
     */
    private const MODULES = [
        'orders' => 'Orders',
        'order_debts' => 'Unpaid Tabs / Order Debts',
    ];

    private const STARTER_ROLES = [
        'Waiter' => [
            'description' => 'Takes orders and collects payment',
            'permissions' => [
                'orders' => ['view' => true, 'create' => true],
                'order_debts' => ['view' => true],
            ],
        ],
        'Bartender' => [
            'description' => 'Prepares orders and marks them ready',
            'permissions' => [
                'orders' => ['view' => true, 'modify' => true],
            ],
        ],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $moduleIds = [];

            foreach (self::MODULES as $name => $description) {
                $moduleIds[$name] = DB::table('modules')->where('name', $name)->value('id')
                    ?: DB::table('modules')->insertGetId([
                        'name' => $name,
                        'description' => $description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            foreach (self::STARTER_ROLES as $roleName => $definition) {
                if (! DB::table('roles')->where('name', $roleName)->exists()) {
                    DB::table('roles')->insert([
                        'name' => $roleName,
                        'description' => $definition['description'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $superAdminId = DB::table('roles')->where('name', 'Super Admin')->value('id');
            $roles = DB::table('roles')->get(['id', 'name']);

            foreach ($roles as $role) {
                foreach ($moduleIds as $moduleName => $moduleId) {
                    if (DB::table('permissions')->where('role_id', $role->id)->where('module_id', $moduleId)->exists()) {
                        continue;
                    }

                    $isSuperAdmin = $role->id === $superAdminId;
                    $starter = self::STARTER_ROLES[$role->name]['permissions'][$moduleName] ?? [];

                    DB::table('permissions')->insert([
                        'role_id' => $role->id,
                        'module_id' => $moduleId,
                        'can_view' => $isSuperAdmin || ($starter['view'] ?? false),
                        'can_create' => $isSuperAdmin || ($starter['create'] ?? false),
                        'can_modify' => $isSuperAdmin || ($starter['modify'] ?? false),
                        'can_delete' => $isSuperAdmin || ($starter['delete'] ?? false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $moduleIds = DB::table('modules')->whereIn('name', array_keys(self::MODULES))->pluck('id');

            DB::table('permissions')->whereIn('module_id', $moduleIds)->delete();
            DB::table('modules')->whereIn('id', $moduleIds)->delete();

            // Starter roles are left in place: users may have been assigned to them.
        });
    }
};
