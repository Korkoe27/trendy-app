<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The bartender hands drinks over and processes them, but may also place food orders for the
     * kitchen (a customer at the bar who wants to eat). This right is deliberately separate from
     * orders.create, which also covers serving and taking payment — those stay with the waiters.
     *
     *   food_orders create = place an order made of kitchen/food items only (never drinks)
     *
     * Super Admin, Admin and Manager get it as well (they already have the fuller orders.create);
     * every other role starts with nothing and can be granted it on the Users page.
     */
    private const MODULE = 'food_orders';

    private const ROLES = [
        'Super Admin' => ['view', 'create'],
        'Admin' => ['view', 'create'],
        'Manager' => ['view', 'create'],
        'Bartender' => ['view', 'create'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', self::MODULE)->value('id')
                ?: DB::table('modules')->insertGetId([
                    'name' => self::MODULE,
                    'description' => 'Place food-only orders',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach (DB::table('roles')->get(['id', 'name']) as $role) {
                if (DB::table('permissions')->where('role_id', $role->id)->where('module_id', $moduleId)->exists()) {
                    continue;
                }

                $actions = self::ROLES[$role->name] ?? [];

                DB::table('permissions')->insert([
                    'role_id' => $role->id,
                    'module_id' => $moduleId,
                    'can_view' => in_array('view', $actions),
                    'can_create' => in_array('create', $actions),
                    'can_modify' => false,
                    'can_delete' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', self::MODULE)->value('id');

            if ($moduleId) {
                DB::table('permissions')->where('module_id', $moduleId)->delete();
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        });
    }
};
