<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\SixDigitPin;
use App\Support\Phone;
use Illuminate\Console\Command;

/**
 * Cutover and recovery tool for the phone + PIN login. Existing users have no phone number until
 * someone gives them one; this is also how a locked-out Super Admin gets back in.
 *
 *   php artisan users:set-phone --list
 *   php artisan users:set-phone garnet 0241234567 --reset-pin
 */
class SetUserPhone extends Command
{
    protected $signature = 'users:set-phone
        {username? : Username (or numeric id) of the user}
        {phone? : Ghana mobile number, e.g. 0241234567}
        {--reset-pin : Also set a new temporary PIN (printed once) and force a PIN change at next login}
        {--pin= : Use this 6-digit PIN instead of a generated one (implies --reset-pin)}
        {--list : List users that still have no phone number}';

    protected $description = 'Set a user\'s phone number (and optionally reset their PIN) for the phone + PIN login';

    public function handle(): int
    {
        if ($this->option('list')) {
            $users = User::whereNull('phone')->orderBy('id')->get(['id', 'name', 'username', 'is_active']);

            if ($users->isEmpty()) {
                $this->info('Every user has a phone number.');

                return self::SUCCESS;
            }

            $this->table(['ID', 'Name', 'Username', 'Active'], $users->map(fn ($u) => [$u->id, $u->name, $u->username, $u->is_active ? 'yes' : 'no']));

            return self::SUCCESS;
        }

        $identifier = $this->argument('username');
        $rawPhone = $this->argument('phone');

        if (! $identifier || ! $rawPhone) {
            $this->error('Give a username and a phone number, or use --list.');

            return self::INVALID;
        }

        $user = User::where('username', $identifier)->when(ctype_digit((string) $identifier), fn ($q) => $q->orWhere('id', $identifier))->first();

        if (! $user) {
            $this->error("No user found for '{$identifier}'.");

            return self::FAILURE;
        }

        $phone = Phone::normalize($rawPhone);

        if (! $phone) {
            $this->error('That is not a valid Ghana mobile number.');

            return self::INVALID;
        }

        if (User::where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            $this->error('That phone number already belongs to another user.');

            return self::FAILURE;
        }

        $changes = ['phone' => $phone];
        $pin = null;

        if ($this->option('reset-pin') || $this->option('pin')) {
            $pin = $this->option('pin') ?: SixDigitPin::generate();

            if (SixDigitPin::isWeak($pin) || ! preg_match('/^\d{6}$/', $pin)) {
                $this->error('The PIN must be 6 digits and not a guessable one (e.g. 123456 or 111111).');

                return self::INVALID;
            }

            $changes += ['password' => $pin, 'must_change_password' => true, 'password_changed_at' => null];
        }

        $user->update($changes);

        $this->info("{$user->name}: phone set to ".Phone::local($phone).'.');

        if ($pin) {
            $this->warn("Temporary PIN: {$pin} (shown once; the user must change it at first sign in).");
        }

        return self::SUCCESS;
    }
}
