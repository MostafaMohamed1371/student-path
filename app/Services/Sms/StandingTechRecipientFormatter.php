<?php

namespace App\Services\Sms;

/**
 * Builds the "recipient" field expected by Standing Tech from our canonical phone digits.
 */
final class StandingTechRecipientFormatter
{
    public function format(string $canonicalDigits): string
    {
        $format = (string) config('standingtech.recipient_format', 'canonical');

        if ($format !== 'composed') {
            return $canonicalDigits;
        }

        $prefix = (string) config('standingtech.recipient_prefix', '');
        $trunk = (string) config('standingtech.mobile_trunk', '');
        $strip = (string) config('standingtech.strip_international_prefix', '966');

        $national = $canonicalDigits;
        if ($strip !== '' && str_starts_with($national, $strip)) {
            $national = substr($national, strlen($strip));
        }

        return $prefix.$trunk.$national;
    }
}
