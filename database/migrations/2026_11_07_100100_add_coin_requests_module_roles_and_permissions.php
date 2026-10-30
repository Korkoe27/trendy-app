<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission map for coin requests (module x action):
     *   coin_requests view   = see the Coin Requests screen
     *                 create = request coins (the snooker attendant)
     *                 modify = mark a request Given (the bar)
     *                 delete = cancel anyone's waiting request and void a given one (manager)
     *
     * Super Admin, Admin and Manager get everything; Bartender can see and give; a starter role
     * "Snooker Attendant" can see and request. Every other role starts with nothing and can be
     * granted it on the Users page.
     */
    private const MODULE = 'coin_requests';

    private const ROLES = [
        'Super Admin' => ['view', 'create', 'modify', 'delete'],
        'Admin' => ['view', 'create', 'modify', 'delete'],
        'Manager' => ['view', 'create', 'modify', 'delete'],
        'Bartender' => ['view', 'modify'],
        'Snooker Attendant' => ['view', 'create'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $moduleId = DB::table('modules')->where('name', self::MODULE)->value('id')
                ?: DB::table('modules')->insertGetId([
                    'name' => self::MODULE,
                    'description' => 'Coin Requests (snooker)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if (! DB::table('roles')->where('name', 'Snooker Attendant')->exists()) {
                DB::table('roles')->insert([
                    'name' => 'Snooker Attendant',
                    'description' => 'Requests snooker coins from the bar',
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
