<?php

namespace App\Support\Auth;

/**
 * Players sign up and sign in with either an email address or a phone number.
 * This decides which one a typed value is and puts phone numbers in a single
 * stored form, so "+216 22 123 456" and "+21622123456" are the same account.
 */
final class LoginIdentifier
{
    /** Optional leading +, then 6 to 15 digits (E.164 allows at most 15). */
    public const PHONE_PATTERN = '/^\+?[0-9]{6,15}$/';

    public static function isEmail(string $value): bool
    {
        return str_contains($value, '@');
    }

    /** The users column the value is looked up in: "email" or "phone". */
    public static function column(string $value): string
    {
        return self::isEmail($value) ? 'email' : 'phone';
    }

    /** Removes spaces, dashes, dots and brackets; a leading 00 becomes +. */
    public static function normalizePhone(string $value): string
    {
        $phone = preg_replace('/[\s\-().]/', '', trim($value)) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        return $phone;
    }

    public static function isValidPhone(string $value): bool
    {
        return preg_match(self::PHONE_PATTERN, self::normalizePhone($value)) === 1;
    }

    /** The value in the form it is stored and compared in. */
    public static function normalize(string $value): string
    {
        return self::isEmail($value) ? trim($value) : self::normalizePhone($value);
    }
}
