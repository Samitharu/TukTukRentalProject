<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use Modules\Booking\Models\Booking;

/**
 * Non-sequential public booking references (brief §8 IDOR prevention): an
 * incrementing id would let one customer guess another's reference. Format:
 * MTR-XXXXXXXX, base32-ish alphabet with ambiguous characters (0/O, 1/I)
 * removed so a reference is easy to read aloud or copy from a printed
 * invoice without transcription errors.
 */
final class BookingReference
{
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const int LENGTH = 8;

    public static function generate(): string
    {
        do {
            $candidate = 'MTR-'.self::randomCode();
        } while (Booking::query()->where('reference', $candidate)->exists());

        return $candidate;
    }

    private static function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, mb_strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function looksValid(string $reference): bool
    {
        return (bool) preg_match('/^MTR-['.preg_quote(self::ALPHABET, '/').']{'.self::LENGTH.'}$/', $reference);
    }

    private function __construct()
    {
        // Static utility — see Str::random() for a similar pattern.
    }
}
