@include('dashboard.partials.iraq_location_map_script', [
    'mapElementId' => 'student-location-map',
    'latInputId' => 'latitude',
    'lngInputId' => 'longitude',
    'landmarkInputId' => 'nearest_landmark',
    'locationPrefix' => 'student',
    'registryKey' => 'student',
    'globalSetLocationFn' => 'studentMapSetLocation',
])
