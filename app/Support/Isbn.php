<?php

namespace App\Support;

/**
 * ISBN normalisation, validation and ISBN-10 -> ISBN-13 conversion.
 * Inventory and the product catalogue are keyed on ISBN-13.
 */
class Isbn
{
    /** Strip hyphens/spaces and uppercase the ISBN-10 'X' check digit. */
    public static function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/[^0-9xX]/', '', $raw) ?? '');
    }

    public static function isValid(string $raw): bool
    {
        $isbn = self::normalize($raw);

        return match (strlen($isbn)) {
            10 => self::isValidIsbn10($isbn),
            13 => self::isValidIsbn13($isbn),
            default => false,
        };
    }

    /** Normalise any valid ISBN-10/13 to ISBN-13, or null if invalid. */
    public static function toIsbn13(string $raw): ?string
    {
        $isbn = self::normalize($raw);

        if (strlen($isbn) === 13 && self::isValidIsbn13($isbn)) {
            return $isbn;
        }

        if (strlen($isbn) === 10 && self::isValidIsbn10($isbn)) {
            $core = '978'.substr($isbn, 0, 9);

            return $core.self::isbn13CheckDigit($core);
        }

        return null;
    }

    private static function isValidIsbn10(string $isbn): bool
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (! ctype_digit($isbn[$i])) {
                return false;
            }
            $sum += ((int) $isbn[$i]) * (10 - $i);
        }
        $check = $isbn[9] === 'X' ? 10 : (ctype_digit($isbn[9]) ? (int) $isbn[9] : -1);
        if ($check < 0) {
            return false;
        }

        return ($sum + $check) % 11 === 0;
    }

    private static function isValidIsbn13(string $isbn): bool
    {
        if (! ctype_digit($isbn)) {
            return false;
        }

        return (string) $isbn[12] === self::isbn13CheckDigit(substr($isbn, 0, 12));
    }

    /** Check digit for the first 12 digits of an ISBN-13. */
    private static function isbn13CheckDigit(string $first12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $first12[$i]) * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
