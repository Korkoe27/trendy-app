<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Support\Facades\{Blade,Auth};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom Blade directive for permission checking
        Blade::if('haspermission', function ($action, $module) {
            $user = Auth::user();

            if (! $user || ! $user->role) {
                return false;
            }

            $permission = Permission::where('role_id', $user->role_id)
                ->whereHas('module', function ($query) use ($module) {
                    $query->where('name', $module);
                })
                ->first();

            if (! $permission) {
                return false;
            }

            $permissionField = 'can_'.$action;

            return $permission->$permissionField ?? false;
        });

        // Check if user has role
        Blade::if('hasrole', function ($roleName) {
            $user = Auth::user();

            return $user && $user->role && $user->role->name === $roleName;
        });
    }
}
