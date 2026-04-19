<?php

namespace App\Services\Phone;

/**
 * Iraqi MSISDN: static country code 964 + exactly 10 national digits supplied by the client.
 * Client must send national number only (no leading 0), e.g. 7701234567 → 9647701234567.
 */
final class PhoneNormalizer
{
    private const string COUNTRY_CALLING_CODE = '964';

    /**
     * Append static 964 to 10-digit national input (digits only, expected length 10 after stripping).
     */
    public function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        return self::COUNTRY_CALLING_CODE.$digits;
    }

    /**
     * National part must be exactly 10 digits and must not start with 0.
     */
    public function isValidIraqiMobile(string $raw): bool
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return (bool) preg_match('/^[1-9]\d{9}$/', $digits);
    }
}
