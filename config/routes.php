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

    /*
    |--------------------------------------------------------------------------
    | Location report filter (governorate / district / sub-district)
    |--------------------------------------------------------------------------
    |
    | Routes without stored location IDs can still match a filter when their
    | start point is within this distance (meters) of a sub-district centroid.
    |
    */
    'location_filter_max_meters' => (int) env('ROUTE_LOCATION_FILTER_MAX_METERS', 5000),

];
