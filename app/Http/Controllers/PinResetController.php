<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogs;
use App\Models\User;
use App\Rules\SixDigitPin;
use App\Services\SmsService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Forgot-PIN flow: phone number -> 6-digit code by SMS -> new PIN. The SMS code is only ever
 * used to set a PIN, never to log in. Codes are stored hashed, expire after 5 minutes and
 * allow 3 tries.
 */
class PinResetController extends Controller
{
    public const CODE_TTL_MINUTES = 5;

    public const MAX_CODE_TRIES = 3;

    public function showRequest()
    {
        return view('pages.auth.forgot-pin');
    }

    public function sendCode(Request $request, SmsService $sms)
    {
        $request->validate(['phone' => 'required|string|max:30']);

        $phone = Phone::normalize($request->phone);

        // Same answer whether or not the number belongs to an account.
        $respond = function () use ($phone) {
            session()->put('pin_reset_phone', $phone ?? 'invalid');

            return redirect()->route('pin.reset.verify')
                ->with('message', 'If that number is registered, a 6-digit code has been sent by SMS.');
        };

        // Limits: 3 codes per number per hour, 10 requests per IP per hour.
        $numberKey = 'pin-reset-send:'.($phone ?? $request->phone);
        $ipKey = 'pin-reset-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($numberKey, 3) || RateLimiter::tooManyAttempts($ipKey, 10)) {
            throw ValidationException::withMessages([
                'phone' => 'Too many requests. Please wait a while before asking for another code.',
            ]);
        }

        RateLimiter::hit($numberKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $user = $phone ? User::where('phone', $phone)->where('is_active', true)->first() : null;

        if (! $user) {
            return $respond();
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($user->id), [
            'hash' => Hash::make($code),
            'tries' => 0,
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        $sms->send($phone, "Your Trendy PIN reset code is {$code}. It expires in ".self::CODE_TTL_MINUTES.' minutes. If you did not ask for this, ignore this message.');

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'pin_reset_requested',
            'description' => 'PIN reset code requested',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode(['ip' => $request->ip()]),
        ]);

        return $respond();
    }

    public function showVerify(Request $request)
    {
        if (! $request->session()->has('pin_reset_phone')) {
            return redirect()->route('pin.reset.request');
        }

        return view('pages.auth.reset-pin', ['phone' => Phone::mask(session('pin_reset_phone'))]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
            'pin' => ['required', 'confirmed', new SixDigitPin],
        ], [
            'code.digits' => 'The code is 6 digits.',
            'pin.confirmed' => 'The PIN confirmation does not match.',
        ]);

        $phone = session('pin_reset_phone');

        if (! $phone || $phone === 'invalid') {
            return $this->invalidCode();
        }

        $user = User::where('phone', $phone)->where('is_active', true)->first();
        $key = $user ? $this->cacheKey($user->id) : null;
        $entry = $key ? Cache::get($key) : null;

        if (! $user || ! $entry) {
            return $this->invalidCode();
        }

        if ($entry['tries'] >= self::MAX_CODE_TRIES) {
            Cache::forget($key);

            return $this->invalidCode('Too many wrong codes. Please request a new one.');
        }

        if (! Hash::check($request->code, $entry['hash'])) {
            $entry['tries']++;
            Cache::put($key, $entry, now()->addMinutes(self::CODE_TTL_MINUTES));

            return $this->invalidCode();
        }

        Cache::forget($key);

        $user->update([
            'password' => $request->pin,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        // A successful reset also lifts any login lockout for that number.
        RateLimiter::clear('login:'.$phone.'|'.$request->ip());

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'pin_reset',
            'description' => 'PIN reset by SMS code',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode(['ip' => $request->ip()]),
        ]);

        session()->forget('pin_reset_phone');

        return redirect()->route('login')->with('message', 'Your PIN has been reset. Sign in with your new PIN.');
    }

    private function invalidCode(string $message = 'That code is not valid or has expired.')
    {
        throw ValidationException::withMessages(['code' => $message]);
    }

    private function cacheKey(int $userId): string
    {
        return 'pin-reset:'.$userId;
    }
}
