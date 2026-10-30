<?php

namespace App\Livewire\Pages;

use App\Http\Controllers\UserAuthController;
use App\Models\ActivityLogs;
use App\Rules\SixDigitPin;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Personal settings: every signed-in user can change their own name and PIN here. The phone number
 * is the login ID, so only an administrator changes it (Users page).
 */
class Settings extends Component
{
    public string $name = '';

    public string $currentPin = '';

    public string $newPin = '';

    public string $newPinConfirmation = '';

    public function mount(): void
    {
        $this->name = (string) Auth::user()->name;
    }

    public function saveName(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
        ], [
            'name.required' => 'Enter your name.',
            'name.min' => 'Your name must be at least 2 characters.',
        ]);

        $user = Auth::user();
        $name = trim(preg_replace('/\s+/', ' ', $this->name));
        $this->name = $name;

        if ($name === $user->name) {
            session()->flash('message', 'Your name is already saved as '.$name.'.');

            return;
        }

        $old = $user->name;
        $user->update(['name' => $name]);

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'profile_update',
            'description' => 'User changed their name',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode(['old_name' => $old, 'new_name' => $name]),
        ]);

        // The sidebar shows the name, so ask it to redraw.
        $this->dispatch('profile-updated');

        session()->flash('message', 'Your name has been updated.');
    }

    public function changePin(): void
    {
        $this->validate([
            'currentPin' => ['required', 'digits:6'],
            'newPin' => ['required', 'different:currentPin', new SixDigitPin],
            'newPinConfirmation' => ['required', 'same:newPin'],
        ], [
            'currentPin.required' => 'Enter your current PIN.',
            'currentPin.digits' => 'Your current PIN is 6 digits.',
            'newPin.required' => 'Enter a new PIN.',
            'newPin.different' => 'The new PIN must be different from your current PIN.',
            'newPinConfirmation.required' => 'Enter the new PIN again to confirm it.',
            'newPinConfirmation.same' => 'The PIN confirmation does not match.',
        ]);

        $user = Auth::user();

        // Same throttle as the forced PIN change, so a session left open cannot be used to guess the PIN.
        $key = 'pin-change:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, UserAuthController::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'currentPin' => 'Too many attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).',
            ]);
        }

        if (! Hash::check($this->currentPin, $user->password)) {
            RateLimiter::hit($key, UserAuthController::LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['currentPin' => 'The current PIN is incorrect.']);
        }

        RateLimiter::clear($key);

        $user->update([
            'password' => $this->newPin,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'password_change',
            'description' => 'User changed their PIN',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode(['changed_at' => now(), 'forced_change' => false, 'source' => 'settings']),
        ]);

        $this->reset(['currentPin', 'newPin', 'newPinConfirmation']);

        session()->flash('message', 'Your PIN has been changed. Use the new PIN next time you sign in.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.pages.settings', [
            'phone' => $user->phone ? Phone::local($user->phone) : null,
            'roleName' => $user->role?->name,
            'pinChangedAt' => $user->password_changed_at,
        ]);
    }
}
