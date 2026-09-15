<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class KrooId
{
    public const MAXIMUM = 1_000_000_000;

    private const CODE_SPACE = 1_000_000_000;

    private const MULTIPLIER = 7_919;

    private const OFFSET = 314_159_265;

    private const MULTIPLIER_INVERSE = 940_017_679;

    public function allocate(): int
    {
        $id = DB::table('kroo_id_allocations')->insertGetId([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        throw_if($id > self::MAXIMUM, RuntimeException::class, 'Kroo ID capacity has been reached.');

        return $id;
    }

    public static function format(int $id): string
    {
        throw_if($id < 1 || $id > self::MAXIMUM, RuntimeException::class, 'Invalid Kroo ID.');

        $encoded = (($id - 1) * self::MULTIPLIER + self::OFFSET) % self::CODE_SPACE;
        $letterValue = intdiv($encoded, 10_000);
        $numbers = str_pad((string) ($encoded % 10_000), 4, '0', STR_PAD_LEFT);
        $letters = '';
        for ($position = 0; $position < 4; $position++) {
            $letters = chr(65 + ($letterValue % 26)).$letters;
            $letterValue = intdiv($letterValue, 26);
        }

        $checksumLetter = chr(65 + ($encoded % 26));
        $checksumDigit = intdiv($encoded, 26) % 10;

        return $letters[0].$numbers[0].$letters[1].$numbers[1].$letters[2].$numbers[2].$letters[3].$numbers[3].$checksumLetter.$checksumDigit;
    }

    public static function parse(string $value): ?int
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^(?:[A-Z]\d){5}$/', $value) === 1) {
            $letters = $value[0].$value[2].$value[4].$value[6];
            $letterValue = 0;
            for ($position = 0; $position < 4; $position++) {
                $letterValue = $letterValue * 26 + ord($letters[$position]) - 65;
            }
            $encoded = $letterValue * 10_000 + (int) ($value[1].$value[3].$value[5].$value[7]);
            if ($encoded >= self::CODE_SPACE) {
                return null;
            }
            if ($value[8] !== chr(65 + ($encoded % 26)) || (int) $value[9] !== intdiv($encoded, 26) % 10) {
                return null;
            }

            $unshifted = ($encoded - self::OFFSET + self::CODE_SPACE) % self::CODE_SPACE;
            $id = ($unshifted * self::MULTIPLIER_INVERSE) % self::CODE_SPACE + 1;

            return $id <= self::MAXIMUM ? $id : null;
        }

        // Accept the shorter interleaved format so existing shared codes continue to work.
        if (preg_match('/^(?:[A-Z]\d){4}$/', $value) === 1) {
            $letters = $value[0].$value[2].$value[4].$value[6];
            $letterValue = 0;
            for ($position = 0; $position < 4; $position++) {
                $letterValue = $letterValue * 26 + ord($letters[$position]) - 65;
            }
            $encoded = $letterValue * 10_000 + (int) ($value[1].$value[3].$value[5].$value[7]);
            if ($encoded >= self::CODE_SPACE) {
                return null;
            }

            $unshifted = ($encoded - self::OFFSET + self::CODE_SPACE) % self::CODE_SPACE;
            $id = ($unshifted * self::MULTIPLIER_INVERSE) % self::CODE_SPACE + 1;

            return $id <= self::MAXIMUM ? $id : null;
        }

        // Accept the first alphanumeric format so previously shared codes continue to work.
        if (preg_match('/^[A-Z]{2}\d{7}$/', $value) === 1) {
            $series = (ord($value[0]) - 65) * 26 + ord($value[1]) - 65;
            $id = $series * 10_000_000 + (int) substr($value, 2) + 1;

            return $id <= self::MAXIMUM ? $id : null;
        }

        $legacy = preg_replace('/^KROO-/i', '', $value);

        return ctype_digit($legacy) && (int) $legacy >= 1 && (int) $legacy <= self::MAXIMUM
            ? (int) $legacy
            : null;
    }
}
