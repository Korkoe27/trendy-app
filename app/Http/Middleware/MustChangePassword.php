<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MustChangePassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
                $user = Auth::user();

        if ($user && $user->must_change_password) {
            // Allow access only to password change routes
            if (!$request->routeIs('password.change', 'password.update', 'logout')) {
                return redirect()->route('password.change')
                    ->with('warning', 'You must change your password before continuing.');
            }
        }
        return $next($request);
    }
}
