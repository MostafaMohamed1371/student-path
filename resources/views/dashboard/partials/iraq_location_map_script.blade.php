@php
    $mapParams = [
        'mapElementId' => $mapElementId ?? 'student-location-map',
        'latInputId' => $latInputId ?? 'latitude',
        'lngInputId' => $lngInputId ?? 'longitude',
        'landmarkInputId' => $landmarkInputId ?? null,
        'formattedInputId' => $formattedInputId ?? null,
        'locationPrefix' => $locationPrefix ?? 'student',
        'registryKey' => $registryKey ?? ($locationPrefix ?? 'student'),
        'globalSetLocationFn' => $globalSetLocationFn ?? null,
        'locationMode' => $locationMode ?? 'form',
    ];
    $mapsProvider = strtolower((string) config('google.maps_provider', 'auto'));
    $useOpenStreetMap = $mapsProvider === 'osm'
        || $mapsProvider === 'openstreetmap'
        || ($mapsProvider === 'auto' && (string) config('google.maps_api_key') === '');
@endphp
@if($useOpenStreetMap)
    @include('dashboard.partials.leaflet_iraq_location_map_script', $mapParams)
@else
    @include('dashboard.partials.google_iraq_location_map_script', $mapParams)
@endif
