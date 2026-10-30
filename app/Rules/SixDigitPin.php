<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A login PIN: exactly six digits and not one of the guessable ones (000000, 123456, 654321,
 * 121212, 123123...). With only a million combinations, weeding these out matters as much as
 * the login lockout does.
 */
class SixDigitPin implements ValidationRule
{
    private const COMMON = ['112233', '445566', '102030', '142536', '696969', '123321', '112211'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        if (! preg_match('/^\d{6}$/', $pin)) {
            $fail('The PIN must be exactly 6 digits.');

            return;
        }

        if (self::isWeak($pin)) {
            $fail('That PIN is too easy to guess. Avoid repeated or sequential digits such as 111111 or 123456.');
        }
    }

    public static function isWeak(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true; // 000000, 777777
        }

        if (in_array($pin, self::COMMON, true)) {
            return true;
        }

        // Repeating blocks: 121212, 123123.
        if (substr($pin, 0, 2) === substr($pin, 2, 2) && substr($pin, 2, 2) === substr($pin, 4, 2)) {
            return true;
        }

        if (substr($pin, 0, 3) === substr($pin, 3, 3)) {
            return true;
        }

        // Runs that step by one in either direction: 123456, 456789, 987654, 543210.
        $digits = array_map('intval', str_split($pin));
        $steps = [];
        for ($i = 1; $i < 6; $i++) {
            $steps[] = $digits[$i] - $digits[$i - 1];
        }

        return count(array_unique($steps)) === 1 && in_array($steps[0], [1, -1], true);
    }

    /** A random PIN that passes the rule above. */
    public static function generate(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::isWeak($pin));

        return $pin;
    }
}
