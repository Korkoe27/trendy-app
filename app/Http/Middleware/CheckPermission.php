<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next,string $module,string $action): Response
    {
        $user = Auth::user();

        if(!$user || !$user->role){
            abort(403, 'Unauthorized access.');
        }

        $permission = Permission::where('role_id',$user->role_id)
        ->whereHas('module',function($query) use ($module){
            $query->where('name',$module);
        })
        ->first();

        if(!$permission){
            abort(403, 'No permissions found for this module.');
        }

        $permissionField = 'can_' . strtolower($action);

        if(!isset($permission->$permissionField) || !$permission->$permissionField){
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
