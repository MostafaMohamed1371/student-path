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
    | Automatic trip status sync
    |--------------------------------------------------------------------------
    |
    | trips:sync-statuses runs every minute (see routes/console.php).
    | - Never started after start window → status CANCELLED
    | - Started and past scheduled end_time → status COMPLETED
    | - Driver POST /api/trips/{id}/start sets ACTIVE when the trip begins
    |
    */

];
