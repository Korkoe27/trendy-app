<?php

namespace App\Support;

/**
 * One place that knows what a phone number looks like. Numbers are stored as E.164 Ghana
 * mobile numbers (+233XXXXXXXXX) so "024 123 4567", "0241234567" and "+233 24 123 4567" all
 * match the same user, customer or payer.
 */
final class Phone
{
    /** @return string|null E.164 (+233...) or null when the input is not a Ghana mobile number */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00233')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '233')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Ghana mobile numbers: 9 digits after the country code, starting with 2 or 5.
        return preg_match('/^[25]\d{8}$/', $digits) ? '+233'.$digits : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** 0241234567, for showing to staff. */
    public static function local(?string $e164): ?string
    {
        $normalized = self::normalize($e164);

        return $normalized ? '0'.substr($normalized, 4) : $e164;
    }

    /** 233241234567, the format SMS and mobile-money gateways expect. */
    public static function international(?string $input): ?string
    {
        $normalized = self::normalize($input);

        return $normalized ? ltrim($normalized, '+') : null;
    }

    /**
     * Mobile network from the number's prefix: mtn, telecel (Vodafone) or airteltigo; null if unknown.
     * Note that numbers can be ported between networks, so treat this as a best guess.
     */
    public static function network(?string $input): ?string
    {
        $normalized = self::normalize($input);

        if (! $normalized) {
            return null;
        }

        return match (substr($normalized, 4, 2)) {
            '24', '25', '53', '54', '55', '59' => 'mtn',
            '20', '50' => 'telecel',
            '26', '27', '56', '57' => 'airteltigo',
            default => null,
        };
    }

    /** 024 •• 4567, for screens that should not show the full number. */
    public static function mask(?string $input): string
    {
        $local = self::local($input);

        if (! $local || strlen($local) < 10) {
            return (string) $local;
        }

        return substr($local, 0, 3).' •• '.substr($local, -4);
    }
}
