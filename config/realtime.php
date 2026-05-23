<?php

return [

    /*
    | Channel/topic naming (PDF): trip_{id}
    | Laravel Echo private channel: private-trip.{id}
    */
    'channel_prefix' => 'trip_',

    'laravel_echo' => [
        'private_channel_template' => 'trip.{tripHistoryId}',
        'broadcaster' => env('BROADCAST_CONNECTION', 'null'),
        'key' => env('PUSHER_APP_KEY'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'ws_host' => env('PUSHER_HOST'),
        'ws_port' => env('PUSHER_PORT', 443),
        'wss_port' => env('PUSHER_PORT', 443),
        'auth_endpoint' => env('BROADCAST_AUTH_URL', '/broadcasting/auth'),
        'force_tls' => env('PUSHER_SCHEME', 'https') === 'https',
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
    ],
];
