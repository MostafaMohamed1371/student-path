@include('dashboard.partials.iraq_location_map_script', [
    'mapElementId' => 'school-map',
    'latInputId' => 'latitude',
    'lngInputId' => 'longitude',
    'landmarkInputId' => 'address',
    'locationPrefix' => 'school',
    'registryKey' => 'school',
])
