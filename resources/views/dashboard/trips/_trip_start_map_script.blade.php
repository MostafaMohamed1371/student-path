@include('dashboard.partials.iraq_location_map_script', [
    'mapElementId' => 'trip_form_start_map',
    'latInputId' => 'trip_form_start_latitude',
    'lngInputId' => 'trip_form_start_longitude',
    'landmarkInputId' => 'trip_form_start_address',
    'locationPrefix' => 'trip_start',
    'registryKey' => 'trip_start',
])
@include('dashboard.partials.iraq_location_cascade_script', [
    'iraqLocationPrefix' => 'trip_start',
    'neighborhoodMultiple' => false,
])
@include('dashboard.partials.iraq_location_map_sync_script', [
    'iraqLocationPrefix' => 'trip_start',
    'mapRegistryKey' => 'trip_start',
    'mapElementId' => 'trip_form_start_map',
])
@include('dashboard.trips._trip_start_map_overlays_script')
