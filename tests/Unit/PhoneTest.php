<?php

namespace Tests\Unit;

use App\Rules\SixDigitPin;
use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_normalises_ghana_numbers_to_e164(string $input): void
    {
        $this->assertSame('+233241234567', Phone::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [
            'local' => ['0241234567'],
            'spaced local' => ['024 123 4567'],
            'dashes' => ['024-123-4567'],
            'international' => ['+233241234567'],
            'international spaced' => ['+233 24 123 4567'],
            'no plus' => ['233241234567'],
            'double zero' => ['00233241234567'],
            'no leading zero' => ['241234567'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_things_that_are_not_ghana_mobile_numbers(?string $input): void
    {
        $this->assertNull(Phone::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'letters' => ['abc'],
            'too short' => ['024123'],
            'too long' => ['02412345678'],
            'landline style' => ['0301234567'],
            'foreign' => ['+14155552671'],
        ];
    }

    public function test_formats_for_display_and_gateways(): void
    {
        $this->assertSame('0241234567', Phone::local('+233241234567'));
        $this->assertSame('233241234567', Phone::international('024 123 4567'));
        $this->assertSame('024 •• 4567', Phone::mask('+233241234567'));
        $this->assertSame('', Phone::mask(null));
    }

    public function test_pin_rule_accepts_good_pins_and_rejects_guessable_ones(): void
    {
        foreach (['482913', '905317', '271828'] as $good) {
            $this->assertFalse(SixDigitPin::isWeak($good), $good);
        }

        foreach (['000000', '111111', '123456', '654321', '234567', '121212', '123123', '112233'] as $weak) {
            $this->assertTrue(SixDigitPin::isWeak($weak), $weak);
        }
    }

    public function test_generated_pins_are_always_valid(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $pin = SixDigitPin::generate();

            $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
            $this->assertFalse(SixDigitPin::isWeak($pin));
        }
    }
}
