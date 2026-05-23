<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (push)
    |--------------------------------------------------------------------------
    |
    | Requires a Firebase service account JSON (FIREBASE_CREDENTIALS).
    | Device tokens are stored in user_fcm_tokens and registered via the API.
    |
    */

    'enabled' => env('FCM_ENABLED', false),

    /**
     * When true, push sends are logged only (no HTTP call to FCM).
     * Defaults to true when FCM_ENABLED is false.
     */
    'mock' => env('FCM_MOCK', env('FCM_ENABLED', false) ? false : true),

    'credentials' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),

];
