<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Static OTP (optional, any environment)
    |--------------------------------------------------------------------------
    |
    | When non-empty, send() stores this 4-digit code and verify() accepts it
    | for login without requiring a matching otp_codes row. Leave empty for
    | normal random OTPs. Automated tests force this to empty via phpunit.xml.
    |
    */
    'static_code' => env('OTP_STATIC_CODE', ''),

    /*
    | Seconds before the same phone can request another OTP. Set to 0 to allow
    | immediate resend. Cooldown is also skipped when static_code is set.
    */
    'resend_seconds' => (int) env('OTP_RESEND_SECONDS', 30),
];
