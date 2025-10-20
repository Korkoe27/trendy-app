<?php

namespace App\Traits;

use App\Models\Permission;

trait HasPermissions
{
    /**
     * Check if user can perform action on module
     */
    public function hasPermission($action, $module)
    {
        if (!$this->role) {
            return false;
        }

        $permission = Permission::where('role_id', $this->role_id)
            ->whereHas('module', function($query) use ($module) {
                $query->where('name', $module);
            })
            ->first();

        if (!$permission) {
            return false;
        }

        $permissionField = 'can_' . $action;
        return $permission->$permissionField ?? false;
    }

    /**
     * Check if user cannot perform action
     */
    public function lacksPermission($action, $module)
    {
        return !$this->hasPermission($action, $module);
    }

    /**
     * Get all permissions for user's role
     */
    public function getPermissions()
    {
        if (!$this->role) {
            return collect();
        }

        return Permission::where('role_id', $this->role_id)
            ->with('module')
            ->get();
    }

    /**
     * Get modules user has access to
     */
    public function getAccessibleModules($action = 'view')
    {
        if (!$this->role) {
            return collect();
        }

        $permissionField = 'can_' . $action;

        return Permission::where('role_id', $this->role_id)
            ->where($permissionField, true)
            ->with('module')
            ->get()
            ->pluck('module');
    }
}