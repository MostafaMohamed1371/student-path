<?php

return [

    /*
    |--------------------------------------------------------------------------
    | StudentWay mobile contract (notifications_backend_api_contract)
    |--------------------------------------------------------------------------
    |
    | GET /api/notifications returns camelCase items unless ?legacy=1.
    | FCM data payload includes notificationId, type, title, body.
    |
    */

    'contract_max_list' => (int) env('NOTIFICATIONS_CONTRACT_MAX_LIST', 500),

    /**
     * Maps in_app_notifications.data.type → contract category:
     * TRIP | ALERT | SCHEDULE | WARNING | LOCATION
     */
    'contract_type_map' => [
        'TRIP_STARTED' => 'TRIP',
        'TRIP_COMPLETED' => 'TRIP',
        'RETURN_TRIP_STARTED' => 'TRIP',
        'RETURN_TRIP_COMPLETED' => 'TRIP',
        'TRIP_REQUEST' => 'SCHEDULE',
        'TRIP_REQUEST_ACCEPTED' => 'SCHEDULE',
        'TRIP_REQUEST_REJECTED' => 'SCHEDULE',
        'TRIP_REQUEST_CANCELLED' => 'SCHEDULE',
        'ABSENCE' => 'SCHEDULE',
        'TRIP_STUDENT_ON_WAY' => 'LOCATION',
        'TRIP_STUDENT_ARRIVED' => 'LOCATION',
        'TRIP_STUDENT_BOARDED' => 'LOCATION',
        'DELAY_ALERT' => 'ALERT',
        'SOS_TRIGGERED' => 'ALERT',
        'SOS_STOPPED' => 'ALERT',
        'CHAT_MESSAGE' => 'WARNING',
        'WALLET_PAYMENT' => 'SCHEDULE',
        'WALLET_TRANSACTION' => 'SCHEDULE',
    ],

    'contract_type_default' => 'WARNING',

];
