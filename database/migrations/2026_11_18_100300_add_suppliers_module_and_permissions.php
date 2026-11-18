<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission map for supplier management (module x action):
     *   suppliers view   = see the supplier directory and their business profiles
     *             create = add suppliers and their product price list
     *             modify = edit profiles and prices
     *             delete = remove a supplier
     *
     * Super Admin, Admin and Manager get everything. Stock Manager can see, create and
     * modify (they run restocking and maintain the price list) but cannot delete.
     * Every other role starts with nothing and can be granted it on the Users page.
     */
    private const MODULE = 'suppliers';

    private const ROLES = [
        'Super Admin' => ['view', 'create', 'modify', 'delete'],
        'Admin' => ['view', 'create', 'modify', 'delete'],
        'Manager' => ['view', 'create', 'modify', 'delete'],
        'Stock Manager' => ['view', 'create', 'modify'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', self::MODULE)->value('id')
                ?: DB::table('modules')->insertGetId([
                    'name' => self::MODULE,
                    'description' => 'Suppliers (business profiles and product price list)',
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
                    'can_modify' => in_array('modify', $actions),
                    'can_delete' => in_array('delete', $actions),
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
