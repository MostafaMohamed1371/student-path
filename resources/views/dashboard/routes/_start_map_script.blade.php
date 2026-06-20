@include('dashboard.partials.iraq_location_map_script', [
    'mapElementId' => 'route_start_map',
    'latInputId' => 'start_latitude',
    'lngInputId' => 'start_longitude',
    'landmarkInputId' => 'start_address',
    'locationPrefix' => 'route',
    'registryKey' => 'route',
])
@include('dashboard.partials.iraq_location_cascade_script', [
    'iraqLocationPrefix' => 'route',
    'neighborhoodMultiple' => false,
])
@include('dashboard.partials.iraq_location_map_sync_script', [
    'iraqLocationPrefix' => 'route',
    'mapRegistryKey' => 'route',
    'mapElementId' => 'route_start_map',
])
@include('dashboard.routes._route_start_map_overlays_script')
