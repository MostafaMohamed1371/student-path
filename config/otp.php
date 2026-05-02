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
    | Seconds before the same phone can call send-otp again. 0 = no cooldown
    | (default). Set e.g. 30 in production to reduce SMS abuse.
    */
    'resend_seconds' => max(0, (int) env('OTP_RESEND_SECONDS', 0)),

    /*
    | Laravel route throttle for POST /api/auth/send-otp (middleware throttle:otp-send).
    | Two limits apply: per normalized phone and per client IP. Lower = stricter.
    */
    'send_throttle_per_phone_per_minute' => max(1, (int) env('OTP_SEND_THROTTLE_PER_PHONE_PER_MINUTE', 120)),
    'send_throttle_per_ip_per_minute' => max(1, (int) env('OTP_SEND_THROTTLE_PER_IP_PER_MINUTE', 300)),
];
