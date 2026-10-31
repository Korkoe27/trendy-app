<?php

namespace App\Livewire\Pages;

use App\Http\Controllers\UserAuthController;
use App\Models\ActivityLogs;
use App\Models\BlockedNumber;
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

    // Block list (Super Admin only)
    public string $blockPhone = '';

    public string $blockReason = '';

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

    // ------------------------------------------------------------------ block list (Super Admin only)

    private function authorizeBlockList(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }

    public function blockNumber(): void
    {
        $this->authorizeBlockList();

        $this->validate([
            'blockPhone' => ['required', 'string', 'max:30'],
            'blockReason' => ['nullable', 'string', 'max:150'],
        ], [
            'blockPhone.required' => 'Enter the mobile number to block.',
        ]);

        $phone = Phone::normalize($this->blockPhone);

        if (! $phone) {
            $this->addError('blockPhone', 'Enter a valid Ghana mobile number.');

            return;
        }

        if (BlockedNumber::where('phone', $phone)->exists()) {
            $this->addError('blockPhone', 'That number is already on the block list.');

            return;
        }

        $user = Auth::user();
        $reason = trim($this->blockReason) !== '' ? trim($this->blockReason) : null;

        $entry = BlockedNumber::create([
            'phone' => $phone,
            'reason' => $reason,
            'blocked_by' => $user->id,
            'blocked_by_name' => $user->name,
        ]);

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'block_number_add',
            'description' => 'Added '.Phone::mask($phone).' to the block list',
            'entity_type' => 'blocked_number',
            'entity_id' => $entry->id,
            'metadata' => json_encode(['phone' => $phone, 'reason' => $reason]),
        ]);

        $this->reset(['blockPhone', 'blockReason']);
        $this->resetErrorBag();

        session()->flash('message', Phone::local($phone).' is now on the block list.');
    }

    public function unblockNumber(int $id): void
    {
        $this->authorizeBlockList();

        $entry = BlockedNumber::find($id);

        if (! $entry) {
            return;
        }

        $user = Auth::user();
        $entry->delete();

        ActivityLogs::create([
            'user_id' => $user->id,
            'action_type' => 'block_number_remove',
            'description' => 'Removed '.Phone::mask($entry->phone).' from the block list',
            'entity_type' => 'blocked_number',
            'entity_id' => $entry->id,
            'metadata' => json_encode(['phone' => $entry->phone]),
        ]);

        session()->flash('message', Phone::local($entry->phone).' was removed from the block list.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.pages.settings', [
            'phone' => $user->phone ? Phone::local($user->phone) : null,
            'roleName' => $user->role?->name,
            'pinChangedAt' => $user->password_changed_at,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'blockedNumbers' => $user->isSuperAdmin() ? BlockedNumber::orderByDesc('id')->get() : collect(),
        ]);
    }
}
