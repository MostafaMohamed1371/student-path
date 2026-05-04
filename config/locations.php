<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Iraq-wide distance reference (WGS84)
    |--------------------------------------------------------------------------
    |
    | Used by GET /api/locations/iraq when the client does not pass latitude
    | and longitude: every neighborhood with coordinates gets distance_km
    | from this point so the payload always includes comparable distances
    | across Iraq. Approximate geographic centre of Iraq.
    |
    */
    'iraq_distance_reference_latitude' => (float) env('LOCATIONS_IRAQ_REF_LATITUDE', 33.2232),

    'iraq_distance_reference_longitude' => (float) env('LOCATIONS_IRAQ_REF_LONGITUDE', 43.6793),

    'iraq_distance_reference_label' => 'Iraq geographic reference (approx. centre)',

];
