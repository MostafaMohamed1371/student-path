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

];
