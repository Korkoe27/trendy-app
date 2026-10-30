<?php

namespace Database\Seeders;

use App\Models\{Module,Permission,Role,User};
use App\Rules\SixDigitPin;
use App\Support\Phone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $modules = [
                ['name' => 'products', 'description' => 'Product Management'],
                ['name' => 'categories', 'description' => 'Category Management'],
                ['name' => 'stocks', 'description' => 'Stock Management'],
                ['name' => 'users', 'description' => 'User Management'],
                ['name' => 'logs', 'description' => 'Activity Logs Management'],
                ['name' => 'analytics', 'description' => 'Analytics Dashboard'],
                ['name' => 'inventory', 'description' => 'Inventory Management'],
                ['name' => 'expenses', 'description' => 'Expense data and Information'],
                ['name' => 'settings', 'description' => 'Application Settings'],
                ['name' => 'debt_payments', 'description' => 'Debt Payments'],
                ['name' => 'orders', 'description' => 'Orders'],
                ['name' => 'order_debts', 'description' => 'Unpaid Tabs / Order Debts'],
                ['name' => 'coin_requests', 'description' => 'Coin Requests (snooker)'],
            ];

            // firstOrCreate: the debt_payments module is also inserted by a migration.
            $createdModules = [];
            foreach ($modules as $module) {
                $createdModules[$module['name']] = Module::firstOrCreate(
                    ['name' => $module['name']],
                    ['description' => $module['description']]
                );
            }

            // Create Super Admin Role
            $superAdmin = Role::create([
                'name' => 'Super Admin',
                'description' => 'Role with all permissions',
            ]);

            // Assign permissions to Super Admin
            foreach ($createdModules as $module) {
                Permission::create([
                    'role_id' => $superAdmin->id,
                    'module_id' => $module->id,
                    'can_create' => true,
                    'can_view' => true,
                    'can_modify' => true,
                    'can_delete' => true,
                ]);
            }

            // Create Manager Role
            $manager = Role::create([
                'name' => 'Manager',
                'description' => 'Role with limited permissions',
            ]);

            // Assign permissions to Manager
            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['products']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            // Repeat permission creation for other modules...
            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['categories']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['stocks']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['analytics']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['logs']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['users']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['inventory']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['expenses']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            // Managers see the order board and may close (settle) any order.
            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['orders']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['order_debts']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            // Managers run the coin requests screen (request, give, cancel, void).
            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['coin_requests']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => true,
            ]);

            Permission::create([
                'role_id' => $manager->id,
                'module_id' => $createdModules['settings']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            // Create Stock Manager Role
            $stockManager = Role::create([
                'name' => 'Stock Manager',
                'description' => 'Role with limited permissions',
            ]);

            // Assign permissions to Stock Manager
            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['products']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            // Repeat permission creation for other modules...
            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['stocks']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);
            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['categories']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);
            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['users']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['logs']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['analytics']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['inventory']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['expenses']->id,
                'can_create' => true,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $stockManager->id,
                'module_id' => $createdModules['settings']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            // Fix the Sales Person role name
            $salesPerson = Role::create([
                'name' => 'Sales Person',  // Corrected name
                'description' => 'Role with limited permissions',
            ]);

            // Assign permissions to Sales Person
            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['products']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            // Repeat permission creation for other modules...
            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['categories']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['stocks']->id,
                'can_create' => false,
                'can_view' => true,
                'can_modify' => true,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['users']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['logs']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['analytics']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['inventory']->id,
                'can_create' => true,
                'can_view' => true,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['expenses']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            Permission::create([
                'role_id' => $salesPerson->id,
                'module_id' => $createdModules['settings']->id,
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ]);

            // Super Admin
            // Sign in is phone + 6-digit PIN. Override with SEED_ADMIN_PHONE / SEED_ADMIN_PIN; otherwise a PIN is generated and printed.
            $adminPin = env('SEED_ADMIN_PIN') ?: SixDigitPin::generate();

            User::create([
                'name' => 'Garnet Koku',
                'username' => 'garnet',
                'phone' => env('SEED_ADMIN_PHONE', '0558731186'),
                'email' => 'garnet@trendy.com',
                'role_id' => $superAdmin->id,
                'password' => $adminPin,
            ]);

            $this->command?->info('Super Admin created. Phone: '.Phone::local(env('SEED_ADMIN_PHONE', '0558731186')).' PIN: '.$adminPin);

            // User::create([
            //     'name' => 'Korkoe Dumashie',
            //     'username' => 'korkoe',
            //     'email' => 'korkoe@trendy.com',
            //     'role_id' => $manager->id,
            //     'password' => bcrypt('manager'),
            // ]);

            // User::create([
            //     'name' => 'Maxwell Wilberforce',
            //     'username' => 'maxwill',
            //     'email' => 'maxwell@trendy.com',
            //     'role_id' => $stockManager->id,
            //     'password' => bcrypt('stocks'),
            // ]);

            // User::create([
            //     'name' => 'Chris Banner',
            //     'username' => 'chris',
            //     'email' => 'chris@trendy.com',
            //     'role_id' => $salesPerson->id,
            //     'password' => bcrypt('sales'),
            // ]);

            // Finalize
            $this->call([
                CategoriesSeeder::class,
                RoleSeeder::class,
                // UserSeeder::class,

                // ProductSeeder::class
            ]);

            DB::commit();  // Commit the transaction
        } catch (\Exception $e) {
            DB::rollBack();  // Rollback the transaction on error
            throw $e;  // Re-throw the exception
        }
    }
}
