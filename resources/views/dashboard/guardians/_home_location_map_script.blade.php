@include('dashboard.partials.iraq_location_map_script', [
    'mapElementId' => 'guardian-home-map',
    'latInputId' => 'guardian_home_latitude',
    'lngInputId' => 'guardian_home_longitude',
    'landmarkInputId' => 'guardian_home_nearest_landmark',
    'formattedInputId' => 'guardian_home_formatted_address',
    'locationPrefix' => 'guardian_home',
    'registryKey' => 'guardian_home',
    'globalSetLocationFn' => 'guardianHomeMapSetLocation',
])
