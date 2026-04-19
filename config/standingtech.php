<?php

return [

    'url' => env('STANDINGTECH_SMS_URL', 'https://gateway.standingtech.com/api/v4/sms/send'),

    // Full Bearer token as issued by Standing Tech (often contains "|").
    'bearer_token' => env('STANDINGTECH_BEARER_TOKEN'),

    'sender_id' => env('STANDINGTECH_SENDER_ID'),

    // e.g. plain, whatsapp — per Standing Tech API.
    'type' => env('STANDINGTECH_TYPE', 'whatsapp'),

    'lang' => env('STANDINGTECH_LANG', 'ar'),

    // When true, SMS is not sent over the network (logs only).
    'mock' => filter_var(env('STANDINGTECH_MOCK', false), FILTER_VALIDATE_BOOLEAN),

    'timeout' => (int) env('STANDINGTECH_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Recipient format
    |--------------------------------------------------------------------------
    |
    | canonical — digits as stored by the app (e.g. 9647XXXXXXXXX for Iraq).
    | composed  — STANDINGTECH_RECIPIENT_PREFIX + STANDINGTECH_MOBILE_TRUNK + national
    |             digits after stripping STANDINGTECH_STRIP_INTERNATIONAL_PREFIX (default 964).
    |
    */
    'recipient_format' => env('STANDINGTECH_RECIPIENT_FORMAT', 'canonical'),

    'recipient_prefix' => env('STANDINGTECH_RECIPIENT_PREFIX', ''),

    'mobile_trunk' => env('STANDINGTECH_MOBILE_TRUNK', ''),

    'strip_international_prefix' => env('STANDINGTECH_STRIP_INTERNATIONAL_PREFIX', '964'),

];
