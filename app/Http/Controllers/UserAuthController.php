<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogs;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    public function login()
    {
        return view('pages.auth.login');
    }

    public function showChangePassword()
    {
        return view('pages.auth.ChangePassword');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ], [
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $user = Auth::user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'password_change',
            'description' => 'User changed their password',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode([
                'changed_at' => now(),
                'forced_change' => $user->must_change_password,
            ]),
        ]);

        return redirect('/')
            ->with('message', 'Password changed successfully!');
    }

    public function signin(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Attempt authentication
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'username' => 'Sorry these credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Check if user is active immediately after successful authentication
        if (! $user->is_active) {
            Auth::logout(); // Log them out immediately
            throw ValidationException::withMessages([
                'username' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

        $request->session()->regenerate();

        $metadata = [
            'username' => $user->username,
            'login_time' => now(),
        ];

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'login',
            'description' => 'User logged in successfully',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode($metadata),
        ]);

        // Redirect to password change if required
        if ($user->must_change_password) {
            return redirect()->route('password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        return redirect('/');
    }
    // public function signin(Request $request)
    // {

    //     // Handle the sign-in logic here
    //     // Validate the request, authenticate the user, etc.
    //             $credentials = $request->validate([
    //         'username' => 'required|string',  // This can be email or username
    //         'password' => 'required|string'
    //     ]);

    //     $user = User::where('username', $credentials['username'])->first();

    //     if (!$user || !Hash::check($credentials['password'], $user->password)) {
    //     throw ValidationException::withMessages([
    //         'username' => 'Sorry these credentials do not match our records.'
    //     ]);
    // }

    //     if (!Auth::attempt($credentials)) {
    //     throw ValidationException::withMessages([
    //         'username' => 'Sorry these credentials do not match our records.'
    //     ]);
    // }

    //     // if(!Auth::attempt($user)){
    //     //     throw ValidationException::withMessages([
    //     //         'username'=>'Sorry these credentials do not match our records.'
    //     //     ]);
    //     // };

    //     $request->session()->regenerate();

    //     $metadata = [
    //         // 'ip_address' => $request->ip(),
    //         'username' => $user->username,
    //         'login_time' => now(),
    //     ];

    //     ActivityLogs::create([
    //         'user_id' => Auth::id(),
    //         'action_type' => 'Log in',
    //         'description' => 'User logged in successfully',
    //         'entity_type' => 'user',
    //         'entity_id' => Auth::id(),
    //         'metadata' => json_encode($metadata),
    //     ]);
    //     return redirect('/');
    //     // return back()->withErrors([
    //     //     'username' => 'The provided credentials do not match our records.',
    //     // ]);
    // }

    public function destroy(Request $request)
    {
        $metadata = [
            // 'ip_address' => $request->ip(),
            'username' => Auth::user()?->username,
            'logout_time' => now(),
        ];
        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'logout',
            'description' => 'User logged out successfully',
            'entity_type' => 'user',
            'entity_id' => Auth::id(),
            'metadata' => json_encode($metadata),
        ]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
