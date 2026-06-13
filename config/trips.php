<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver arrival proximity (meters)
    |--------------------------------------------------------------------------
    |
    | When marking ARRIVED, the driver must be within this distance of the
    | student's pickup coordinates (latitude/longitude on the student record).
    |
    */
    'driver_arrival_max_distance_meters' => (int) env('TRIP_DRIVER_ARRIVAL_MAX_DISTANCE_METERS', 50),

    /*
    |--------------------------------------------------------------------------
    | Waiting window after ARRIVED (minutes)
    |--------------------------------------------------------------------------
    |
    | Returned to the mobile app to start the boarding countdown timer.
    |
    */
    'default_waiting_minutes' => (int) env('TRIP_DEFAULT_WAITING_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Driver trip start window (minutes)
    |--------------------------------------------------------------------------
    |
    | Trip start_time / end_time come from the dashboard create-trip form.
    | POST /api/trips/{id}/start is allowed when now() is between
    | start_time - early and min(end_time, start_time + late).
    |
    */
    'driver_start_early_minutes' => (int) env('TRIP_DRIVER_START_EARLY_MINUTES', 10),
    'driver_start_late_minutes' => (int) env('TRIP_DRIVER_START_LATE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Trip lifecycle in-app / FCM notifications (start, end, student ARRIVED)
    |--------------------------------------------------------------------------
    */
    'notifications_enabled' => env('TRIP_NOTIFICATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Live driver location (Firebase Realtime Database + API)
    |--------------------------------------------------------------------------
    |
    | Store: auto (firebase when FIREBASE_DATABASE_URL + credentials exist), firebase, cache
    | Path: trips/{tripId}/tracking — mobile apps can listen at .../location
    |
    */
    'location_store' => env('TRIP_LOCATION_STORE', 'auto'),
    'location_firebase_path' => 'trips/{tripId}/tracking',
    'location_cache_ttl_seconds' => (int) env('TRIP_LOCATION_CACHE_TTL_SECONDS', 86400),
    'location_max_updates_per_minute' => (int) env('TRIP_LOCATION_MAX_UPDATES_PER_MINUTE', 30),

    'location_broadcast_enabled' => env('TRIP_LOCATION_BROADCAST_ENABLED', true),
    'location_broadcast_event' => env('TRIP_LOCATION_BROADCAST_EVENT', 'driver.location.updated'),

    /*
    |--------------------------------------------------------------------------
    | Automatic trip status sync
    |--------------------------------------------------------------------------
    |
    | trips:sync-statuses runs every minute (see routes/console.php).
    | - Never started after start window → status CANCELLED
    | - Started and past scheduled end_time → status COMPLETED
    | - Driver POST /api/trips/{id}/start sets ACTIVE when the trip begins
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Recurring trip templates
    |--------------------------------------------------------------------------
    |
    | When students are assigned to a trip it becomes a template. The spawner
    | clones it on each school working day for this many days ahead.
    |
    */
    'recurring_spawn_horizon_days' => (int) env('TRIP_RECURRING_SPAWN_HORIZON_DAYS', 30),

];
