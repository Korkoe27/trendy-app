<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Log};
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    public function login()
    {
        return view('pages.auth.login');
    }

    public function signin(Request $request)
    {



        // Handle the sign-in logic here
        // Validate the request, authenticate the user, etc.
                $user = $request->validate([
            'username' => 'required|string',  // This can be email or username
            'password' => 'required|string'
        ]);


        if(!Auth::attempt($user)){
            throw ValidationException::withMessages([
                'username'=>'Sorry these credentials do not match our records.'
            ]);
        };


        $request->session()->regenerate();

        Log::info("Hey");
        // Example:
        // $credentials = $request->only('username', 'password');
        
        // if (auth()->attempt($credentials)) {
        //     return redirect()->intended('dashboard'); // Redirect to intended page after login
        // }
        return redirect('/');
        // return back()->withErrors([
        //     'username' => 'The provided credentials do not match our records.',
        // ]);
    }


    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'logout',
            'description' => 'User logged out successfully',
            'entity_type' => 'user',
            'entity_id' => Auth::id(),
            'metadata' => ''
        ]);

        return redirect()->route('login');
    }
}
