<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route corridor (start point → school)
    |--------------------------------------------------------------------------
    |
    | Students whose home location is within this distance (meters) of the
    | straight line from the driver start point to the school are eligible
    | for assignment on that route.
    |
    */
    'corridor_max_meters' => (int) env('ROUTE_CORRIDOR_MAX_METERS', 3000),

];
