<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Log};
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    // public function handle(Request $request, Closure $next,string $module,string $action): Response
    // {
    //     $user = Auth::user();

    //     if(!$user || !$user->role){
    //         abort(403, 'Unauthorized access.');
    //     }

    //     $permission = Permission::where('role_id',$user->role_id)
    //     ->whereHas('module',function($query) use ($module){
    //         $query->where('name',$module);
    //     })
    //     ->first();

    //     if(!$permission){
    //         abort(403, 'No permissions found for this module.');
    //     }

    //     $permissionField = 'can_' . strtolower($action);

    //     if(!isset($permission->$permissionField) || !$permission->$permissionField){
    //         abort(403, 'You do not have permission to perform this action.');
    //     }

    //     return $next($request);
    // }

    public function handle(Request $request, Closure $next, string $module, string $action): Response
{

    Log::info("Checking sessions");
    $user = Auth::user();

    if (!$user || !$user->role) {
        abort(403, 'Unauthorized access.');
    }

    // Cache permissions in the request for the current cycle
    $cacheKey = "user_permissions_{$user->id}";
    
    $permissions = $request->attributes->get($cacheKey);
    
    if ($permissions === null) {
        $permissions = Permission::where('role_id', $user->role_id)
            ->with('module:id,name') // Eager load only needed columns
            ->get()
            ->keyBy('module.name');
        
        $request->attributes->set($cacheKey, $permissions);
    }

    $permission = $permissions->get($module);

    if (!$permission) {
        abort(403, 'No permissions found for this module.');
    }

    $permissionField = 'can_' . strtolower($action);

    if (!isset($permission->$permissionField) || !$permission->$permissionField) {
        abort(403, 'You do not have permission to perform this action.');
    }

    return $next($request);
}
}
