<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding (login card subtitle under "Welcome")
    |--------------------------------------------------------------------------
    */
    'brand' => env('DASHBOARD_BRAND', env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Seeded dashboard user (DatabaseSeeder)
    |--------------------------------------------------------------------------
    */
    'seed_phone_national' => env('DASHBOARD_SEED_PHONE', '7701234567'),

    'seed_password' => env('DASHBOARD_SEED_PASSWORD', '12345678'),

];
