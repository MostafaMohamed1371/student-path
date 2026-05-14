<?php

return [
    'places_api_key' => env('GOOGLE_PLACES_API_KEY', ''),

    /*
    | BCP-47 language for Places (e.g. ar, en). Empty uses primary language from app.locale.
    */
    'places_language' => env('GOOGLE_PLACES_LANGUAGE', ''),

    /*
    | Region bias (ccTLD / region code), e.g. iq — improves local relevance without hard-filtering.
    */
    'places_region' => env('GOOGLE_PLACES_REGION', 'iq'),

    /*
    | Autocomplete components filter (e.g. country:iq). Empty string disables the filter.
    */
    'places_components' => env('GOOGLE_PLACES_COMPONENTS', 'country:iq'),

    /*
    | Default Place Details field mask when the client omits ?fields= (controls billing SKU breadth).
    | https://developers.google.com/maps/documentation/places/web-service/details
    */
    'places_details_default_fields' => env(
        'GOOGLE_PLACES_DETAIL_FIELDS',
        'place_id,formatted_address,name,geometry/location,geometry/viewport'
    ),
];
