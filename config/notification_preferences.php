<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default notification toggles (mobile settings screen)
    |--------------------------------------------------------------------------
    |
    | Persisted per user in user_notification_preferences. GET /api/user/settings/notifications
    | returns defaults merged with stored values.
    |
    */

    'defaults' => [
        'tripNotifications' => [
            'busMovement' => true,
            'busArrival' => true,
            'returnTrip' => true,
            'driverDelay' => true,
            'sos' => true,
        ],
        'paymentNotifications' => [
            'installmentReminder' => true,
            'paymentConfirmation' => true,
        ],
        'chatNotifications' => [
            'messages' => true,
        ],
        'systemNotifications' => [
            'appUpdates' => true,
        ],
    ],

    /*
    | Maps in_app_notifications.data.type to a preference path [group, key].
    | Unknown types are allowed (push sent) unless you add them here.
    */
    'push_type_map' => [
        'CHAT_MESSAGE' => ['chatNotifications', 'messages'],
        'DELAY_ALERT' => ['tripNotifications', 'driverDelay'],
        'SOS_TRIGGERED' => ['tripNotifications', 'sos'],
        'WALLET_PAYMENT' => ['paymentNotifications', 'paymentConfirmation'],
        'TRIP_REQUEST' => ['tripNotifications', 'busMovement'],
        'TRIP_REQUEST_ACCEPTED' => ['tripNotifications', 'busMovement'],
        'TRIP_REQUEST_REJECTED' => ['tripNotifications', 'busMovement'],
        'TRIP_STARTED' => ['tripNotifications', 'busMovement'],
        'TRIP_COMPLETED' => ['tripNotifications', 'busMovement'],
        'RETURN_TRIP_STARTED' => ['tripNotifications', 'returnTrip'],
        'RETURN_TRIP_COMPLETED' => ['tripNotifications', 'returnTrip'],
        'TRIP_STUDENT_ARRIVED' => ['tripNotifications', 'busArrival'],
    ],

];
