@php
    $trip = $trip ?? null;
    $isReturnTrip = (bool) ($isReturnTrip ?? false);
    $returnRoutePathSeed = $returnRoutePathSeed ?? null;

    $locationValue = old('location');
    if ($locationValue === null) {
        $locationValue = is_array($returnRoutePathSeed)
            ? ($returnRoutePathSeed['location'] ?? $trip?->location)
            : $trip?->location;
    }

    $distanceValue = old('distance_km');
    if ($distanceValue === null) {
        $distanceValue = is_array($returnRoutePathSeed)
            ? ($returnRoutePathSeed['distance_km'] ?? $trip?->distance_km ?? 0)
            : ($trip?->distance_km ?? 0);
    }

    $startAddressValue = old('start_address');
    if ($startAddressValue === null) {
        $startAddressValue = is_array($returnRoutePathSeed)
            ? ($returnRoutePathSeed['start_address'] ?? $trip?->start_address)
            : $trip?->start_address;
    }

    $startLatValue = old('start_latitude');
    if ($startLatValue === null) {
        $startLatValue = is_array($returnRoutePathSeed)
            ? ($returnRoutePathSeed['route_start_latitude'] ?? $trip?->start_latitude)
            : $trip?->start_latitude;
    }

    $startLngValue = old('start_longitude');
    if ($startLngValue === null) {
        $startLngValue = is_array($returnRoutePathSeed)
            ? ($returnRoutePathSeed['route_start_longitude'] ?? $trip?->start_longitude)
            : $trip?->start_longitude;
    }

    if (is_array($returnRoutePathSeed) && old('location') === null && (float) ($distanceValue ?? 0) <= 0) {
        if (filled($returnRoutePathSeed['location'] ?? null)) {
            $locationValue = $returnRoutePathSeed['location'];
        }
        if (filled($returnRoutePathSeed['distance_km'] ?? null)) {
            $distanceValue = $returnRoutePathSeed['distance_km'];
        }
    }
@endphp

@include('dashboard.partials.iraq_location_fields', array_merge($locationForm ?? [], [
    'iraqLocationPrefix' => 'trip_start',
    'fieldPrefix' => 'start_',
    'neighborhoodMultiple' => false,
]))

<label style="grid-column:1 / -1;">
    <span id="trip_form_start_map_label">{{ $isReturnTrip ? __('dashboard.trip_start_on_map_return') : __('dashboard.trip_start_on_map') }}</span>
    <input id="trip_form_start_address" name="start_address" value="{{ $startAddressValue }}" readonly placeholder="{{ __('dashboard.trip_start_address_placeholder') }}">
</label>
<div style="grid-column:1 / -1;">
    <div id="trip_form_start_map" style="height:320px;border:1px solid #cbd5e1;border-radius:10px;"></div>
</div>
<label>
    <span>{{ __('dashboard.latitude') }}</span>
    <input id="trip_form_start_latitude" name="start_latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ $startLatValue }}" placeholder="33.3128000">
</label>
<label>
    <span>{{ __('dashboard.longitude') }}</span>
    <input id="trip_form_start_longitude" name="start_longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ $startLngValue }}" placeholder="44.3615000">
</label>

<label style="grid-column:1 / -1;">
    <span id="trip_form_route_path_label">{{ $isReturnTrip ? __('dashboard.trip_route_path_return') : __('dashboard.trip_route_path') }}</span>
    <input id="trip_form_location" name="location" value="{{ $locationValue }}" readonly placeholder="{{ $isReturnTrip ? __('dashboard.trip_route_path_return_placeholder') : __('dashboard.trip_route_path_placeholder') }}">
</label>
<p id="trip_form_route_hint" class="help" style="grid-column:1 / -1;margin:0;display:none;"></p>

<label>
    <span>{{ __('dashboard.distance_km') }}</span>
    <input id="trip_form_distance_km" type="number" step="0.01" min="0" name="distance_km" value="{{ $distanceValue }}" required readonly>
</label>

<script>
    window.tripMapInitialReturnPath = @json(is_array($returnRoutePathSeed) ? $returnRoutePathSeed : null);
</script>
