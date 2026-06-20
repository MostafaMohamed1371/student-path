@include('dashboard.partials.iraq_location_map_script', [
    'mapElementId' => 'driver_service_area_map',
    'latInputId' => 'driver_service_area_latitude',
    'lngInputId' => 'driver_service_area_longitude',
    'locationPrefix' => 'driver_service_area',
    'registryKey' => 'driver_service_area',
    'locationMode' => 'driver',
])
@include('dashboard.partials.driver_iraq_location_map_sync_script')
