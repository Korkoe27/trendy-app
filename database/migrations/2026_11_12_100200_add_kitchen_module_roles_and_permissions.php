<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission map for the kitchen (module x action):
     *   kitchen view   = see the kitchen dashboard (food orders, foodstuff, shopping lists)
     *           create = add foodstuff and make shopping lists (the chef)
     *           modify = mark food orders ready (the chef)
     *           delete = cancel a shopping list
     *
     * Super Admin, Admin and Manager get everything; a starter role "Chef" can see, create and
     * mark food ready. Every other role starts with nothing and can be granted it on the Users
     * page. Marking a list as bought (it creates the expense) needs the expenses right, so the
     * accountant and the admins do it.
     */
    private const MODULE = 'kitchen';

    private const ROLES = [
        'Super Admin' => ['view', 'create', 'modify', 'delete'],
        'Admin' => ['view', 'create', 'modify', 'delete'],
        'Manager' => ['view', 'create', 'modify', 'delete'],
        'Chef' => ['view', 'create', 'modify'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', self::MODULE)->value('id')
                ?: DB::table('modules')->insertGetId([
                    'name' => self::MODULE,
                    'description' => 'Kitchen (food orders and shopping lists)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if (! DB::table('roles')->where('name', 'Chef')->exists()) {
                DB::table('roles')->insert([
                    'name' => 'Chef',
                    'description' => 'Prepares food orders and makes shopping lists',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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

            // The starter role is left in place: users may have been assigned to it.
        });
    }
};
