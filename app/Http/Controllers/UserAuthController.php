<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogs;
use App\Models\User;
use App\Rules\SixDigitPin;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    /** Wrong PINs allowed per phone + IP before the login is locked. */
    public const MAX_ATTEMPTS = 5;

    /** Lockout length in seconds. */
    public const LOCKOUT_SECONDS = 900;

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
            'current_pin' => 'required|digits:6',
            'pin' => ['required', 'confirmed', 'different:current_pin', new SixDigitPin],
        ], [
            'current_pin.digits' => 'Your current PIN is 6 digits.',
            'pin.different' => 'The new PIN must be different from your current PIN.',
            'pin.confirmed' => 'The PIN confirmation does not match.',
        ]);

        $user = Auth::user();

        // The user is already signed in, but the change is still gated on the current PIN and
        // is rate limited like a login so a left-open session cannot be used to guess it.
        $key = 'pin-change:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'current_pin' => 'Too many attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).',
            ]);
        }

        if (! Hash::check($request->current_pin, $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'current_pin' => 'The current PIN is incorrect.',
            ]);
        }

        RateLimiter::clear($key);

        $forced = $user->must_change_password;

        $user->update([
            'password' => $request->pin,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'password_change',
            'description' => 'User changed their PIN',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode([
                'changed_at' => now(),
                'forced_change' => $forced,
            ]),
        ]);

        return redirect('/')
            ->with('message', 'PIN changed successfully!');
    }

    public function signin(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
            'pin' => 'required|digits:6',
        ], [
            'pin.digits' => 'Your PIN is 6 digits.',
        ]);

        $phone = Phone::normalize($request->phone);
        $remember = $request->filled('remember');

        // One bucket per phone + IP. The error text is identical for unknown numbers, wrong PINs
        // and deactivated accounts so the form cannot be used to find out who has an account.
        $key = 'login:'.Str::lower($phone ?? $request->phone).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->logFailure($phone, 'login_locked');

            throw ValidationException::withMessages([
                'phone' => 'Too many failed attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).',
            ]);
        }

        if (! $phone || ! Auth::attempt(['phone' => $phone, 'password' => $request->pin, 'is_active' => true], $remember)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            $this->logFailure($phone, 'login_failed');

            throw ValidationException::withMessages([
                'phone' => 'The phone number or PIN is incorrect.',
            ]);
        }

        RateLimiter::clear($key);

        $user = Auth::user();

        $request->session()->regenerate();

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'login',
            'description' => 'User logged in successfully',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode([
                'username' => $user->username,
                'login_time' => now(),
            ]),
        ]);

        if ($user->must_change_password) {
            return redirect()->route('password.change')
                ->with('warning', 'You must set a new PIN before continuing.');
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request)
    {
        $metadata = [
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

    /** Failed and locked-out logins are recorded without the PIN or the full phone number. */
    private function logFailure(?string $phone, string $type): void
    {
        $context = ['phone' => Phone::mask($phone), 'ip' => request()->ip()];
        $description = $type === 'login_locked' ? 'Login blocked after repeated failures' : 'Failed login attempt';

        Log::warning($description, $context);

        // activity_logs needs a user, so only numbers that belong to an account show up on the Logs page.
        $user = $phone ? User::where('phone', $phone)->first() : null;

        if ($user) {
            ActivityLogs::create([
                'user_id' => $user->id,
                'action_type' => $type,
                'description' => $description,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'metadata' => json_encode($context),
            ]);
        }
    }
}
