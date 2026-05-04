<?php

return [
    'enabled' => filter_var(env('QI_CARD_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * When QiCard is enabled, block POST /api/wallet/recharge (instant credit) so top-ups
     * go through the gateway only. Set QI_CARD_BLOCK_DIRECT_RECHARGE=false to allow both.
     */
    'block_direct_recharge' => filter_var(env('QI_CARD_BLOCK_DIRECT_RECHARGE', true), FILTER_VALIDATE_BOOLEAN),

    'api_host' => rtrim((string) env('QI_CARD_API_HOST', ''), '/'),
    'username' => (string) env('QI_CARD_USERNAME', ''),
    'password' => (string) env('QI_CARD_PASSWORD', ''),
    'terminal_id' => (string) env('QI_CARD_TERMINAL_ID', ''),
    'timeout' => max(5, (int) env('QI_CARD_TIMEOUT', 30)),

    /**
     * Absolute URLs sent to QiCard. If unset, built from APP_URL + path below.
     */
    'finish_payment_url' => env('QI_CARD_FINISH_PAYMENT_URL'),
    'notification_url' => env('QI_CARD_NOTIFICATION_URL'),

    'finish_payment_path' => env('QI_CARD_FINISH_PATH', '/api/wallet/payments/qicard/finish'),
    'notification_path' => env('QI_CARD_NOTIFICATION_PATH', '/api/webhooks/qicard'),
];
