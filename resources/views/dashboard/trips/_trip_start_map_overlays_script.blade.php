<style>
    .trip-map-marker--school {
        width: 14px;
        height: 14px;
        margin-left: -7px;
        margin-top: -7px;
        border-radius: 50%;
        background: #16a34a;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
    }
    .trip-map-marker--suggestion {
        width: 12px;
        height: 12px;
        margin-left: -6px;
        margin-top: -6px;
        border-radius: 50%;
        background: #ea580c;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
        cursor: pointer;
    }
</style>
<script>
(function () {
    const registryKey = 'trip_start';
    const latInput = document.getElementById('trip_form_start_latitude');
    const lngInput = document.getElementById('trip_form_start_longitude');
    const addressInput = document.getElementById('trip_form_start_address');
    const locationInput = document.getElementById('trip_form_location');
    const distanceKmInput = document.getElementById('trip_form_distance_km');
    const schoolSelect = document.getElementById('trip_form_school_id');
    const tripTypeSelect = document.getElementById('trip_form_trip_type');
    const mapHelp = @json(__('dashboard.trip_map_help'));
    const locationStartToEnd = @json(__('dashboard.trip_location_start_to_end', ['start' => '__START__', 'end' => '__END__']));
    const locationStartOnly = @json(__('dashboard.trip_location_start_only', ['start' => '__START__']));
    const locationEndOnly = @json(__('dashboard.trip_location_end_only', ['end' => '__END__']));
    const locationSchoolToPickupStart = @json(__('dashboard.trip_location_school_to_pickup_start', ['start' => '__START__', 'end' => '__END__']));

    if (!latInput || !lngInput || !addressInput) {
        return;
    }

    let registry = null;
    let map = null;
    let mapProvider = 'leaflet';
    let schoolMarker = null;
    let endMarker = null;
    let routeLine = null;
    let suggestionMarkers = [];
    let returnEndLat = null;
    let returnEndLng = null;
    let returnEndAddress = '';
    let returnTripMode = false;

    function getRegistry() {
        return (window.IraqLocationMapRegistry || {})[registryKey] || null;
    }

    function parseCoord(value) {
        if (value === '' || value === null || value === undefined) {
            return null;
        }
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : null;
    }

    function isReturnTripType() {
        const value = tripTypeSelect ? String(tripTypeSelect.value || '') : '';
        return value.endsWith('_RETURN');
    }

    function updateReturnTripMode() {
        returnTripMode = isReturnTripType();
        window.tripMapDisableStartPick = returnTripMode;
    }

    function haversineKm(lat1, lon1, lat2, lon2) {
        const earthKm = 6371.0;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLon / 2) * Math.sin(dLon / 2);

        return Math.round(earthKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)) * 100) / 100;
    }

    function locationFromStartAndEnd(start, end) {
        if (start === '' && end === '') {
            return '';
        }
        if (start === '') {
            return locationEndOnly.replace('__END__', end);
        }
        if (end === '') {
            return locationStartOnly.replace('__START__', start);
        }

        return locationStartToEnd.replace('__START__', start).replace('__END__', end);
    }

    function returnLocationFromStartAndEnd(start, end) {
        if (start === '' && end === '') {
            return '';
        }
        if (start === '') {
            return locationEndOnly.replace('__END__', end);
        }
        if (end === '') {
            return locationStartOnly.replace('__START__', start);
        }

        return locationSchoolToPickupStart.replace('__START__', start).replace('__END__', end);
    }

    function selectedSchoolData() {
        if (!schoolSelect || schoolSelect.selectedIndex < 0) {
            return { address: '', latitude: null, longitude: null };
        }
        const opt = schoolSelect.options[schoolSelect.selectedIndex];
        return {
            address: opt.getAttribute('data-address') || '',
            latitude: parseCoord(opt.getAttribute('data-latitude')),
            longitude: parseCoord(opt.getAttribute('data-longitude')),
        };
    }

    function removeSchoolMarker() {
        if (!schoolMarker) {
            return;
        }
        if (mapProvider === 'google') {
            schoolMarker.setMap(null);
        } else {
            map.removeLayer(schoolMarker);
        }
        schoolMarker = null;
    }

    function removeEndMarker() {
        if (!endMarker) {
            return;
        }
        if (mapProvider === 'google') {
            endMarker.setMap(null);
        } else {
            map.removeLayer(endMarker);
        }
        endMarker = null;
    }

    function removeRouteLine() {
        if (!routeLine) {
            return;
        }
        if (mapProvider === 'google') {
            routeLine.setMap(null);
        } else {
            map.removeLayer(routeLine);
        }
        routeLine = null;
    }

    function drawRouteLine() {
        removeRouteLine();
        if (!map) {
            return;
        }

        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        const school = selectedSchoolData();

        if (returnTripMode && returnEndLat !== null && returnEndLng !== null) {
            const lineStartLat = school.latitude !== null ? school.latitude : startLat;
            const lineStartLng = school.longitude !== null ? school.longitude : startLng;
            if (lineStartLat === null || lineStartLng === null) {
                return;
            }
            if (mapProvider === 'google') {
                routeLine = new google.maps.Polyline({
                    map: map,
                    path: [
                        { lat: lineStartLat, lng: lineStartLng },
                        { lat: returnEndLat, lng: returnEndLng },
                    ],
                    strokeColor: '#2563eb',
                    strokeOpacity: 0.75,
                    strokeWeight: 3,
                    icons: [{
                        icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 3 },
                        offset: '0',
                        repeat: '12px',
                    }],
                });
            } else {
                routeLine = L.polyline(
                    [[lineStartLat, lineStartLng], [returnEndLat, returnEndLng]],
                    { color: '#2563eb', weight: 3, opacity: 0.75, dashArray: '6,8' },
                ).addTo(map);
            }

            return;
        }

        if (startLat === null || startLng === null || school.latitude === null || school.longitude === null) {
            return;
        }

        if (mapProvider === 'google') {
            routeLine = new google.maps.Polyline({
                map: map,
                path: [
                    { lat: startLat, lng: startLng },
                    { lat: school.latitude, lng: school.longitude },
                ],
                strokeColor: '#2563eb',
                strokeOpacity: 0.75,
                strokeWeight: 3,
                icons: [{
                    icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 3 },
                    offset: '0',
                    repeat: '12px',
                }],
            });
        } else {
            routeLine = L.polyline(
                [[startLat, startLng], [school.latitude, school.longitude]],
                { color: '#2563eb', weight: 3, opacity: 0.75, dashArray: '6,8' },
            ).addTo(map);
        }
    }

    function fitMapBounds() {
        if (!map) {
            return;
        }

        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        const school = selectedSchoolData();
        const points = [];

        if (startLat !== null && startLng !== null) {
            points.push({ lat: startLat, lng: startLng });
        }
        if (school.latitude !== null && school.longitude !== null) {
            points.push({ lat: school.latitude, lng: school.longitude });
        }
        if (returnTripMode && returnEndLat !== null && returnEndLng !== null) {
            points.push({ lat: returnEndLat, lng: returnEndLng });
        }

        if (points.length >= 2) {
            if (mapProvider === 'google') {
                const bounds = new google.maps.LatLngBounds();
                points.forEach(function (point) {
                    bounds.extend(point);
                });
                google.maps.event.trigger(map, 'resize');
                map.fitBounds(bounds, 40);
            } else {
                map.fitBounds(points.map(function (point) {
                    return [point.lat, point.lng];
                }), { padding: [40, 40] });
            }
        } else if (points.length === 1) {
            if (mapProvider === 'google') {
                map.setCenter(points[0]);
                map.setZoom(13);
            } else {
                map.setView([points[0].lat, points[0].lng], 13);
            }
        }
    }

    function syncEndMarker() {
        removeEndMarker();
        if (!map || !returnTripMode || returnEndLat === null || returnEndLng === null) {
            return;
        }

        if (mapProvider === 'google') {
            endMarker = new google.maps.Marker({
                map: map,
                position: { lat: returnEndLat, lng: returnEndLng },
            });
        } else {
            endMarker = L.marker([returnEndLat, returnEndLng]).addTo(map);
        }
    }

    function syncSchoolMarker() {
        const school = selectedSchoolData();
        removeSchoolMarker();

        if (!map || school.latitude === null || school.longitude === null) {
            syncEndMarker();
            drawRouteLine();
            fitMapBounds();
            return;
        }

        if (mapProvider === 'google') {
            schoolMarker = new google.maps.Marker({
                map: map,
                position: { lat: school.latitude, lng: school.longitude },
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 7,
                    fillColor: '#16a34a',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                },
                clickable: false,
                zIndex: 2,
            });
        } else {
            schoolMarker = L.marker([school.latitude, school.longitude], {
                icon: L.divIcon({
                    className: 'trip-map-marker--school',
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                }),
                interactive: false,
            }).addTo(map);
        }

        syncEndMarker();
        drawRouteLine();
        fitMapBounds();
    }

    function syncRoutePathFields() {
        const start = addressInput.value != null ? String(addressInput.value).trim() : '';
        const school = selectedSchoolData();

        if (returnTripMode) {
            if (returnEndLat === null || returnEndLng === null) {
                drawRouteLine();
                fitMapBounds();
                return;
            }

            const end = returnEndAddress !== '' ? returnEndAddress : '';
            if (locationInput && start !== '' && end !== '') {
                locationInput.value = returnLocationFromStartAndEnd(start, end);
            }

            const startLat = parseCoord(latInput.value);
            const startLng = parseCoord(lngInput.value);
            if (distanceKmInput && startLat !== null && startLng !== null) {
                distanceKmInput.value = String(haversineKm(startLat, startLng, returnEndLat, returnEndLng));
            }

            syncEndMarker();
            drawRouteLine();
            fitMapBounds();
            return;
        }

        const end = school.address != null ? String(school.address).trim() : '';
        if (locationInput) {
            locationInput.value = locationFromStartAndEnd(start, end);
        }

        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        if (distanceKmInput && startLat !== null && startLng !== null && school.latitude !== null && school.longitude !== null) {
            distanceKmInput.value = String(haversineKm(startLat, startLng, school.latitude, school.longitude));
        }

        syncEndMarker();
        drawRouteLine();
        fitMapBounds();
    }

    function setStartLocation(newLat, newLng, skipSync) {
        registry = registry || getRegistry();
        if (registry && typeof registry.setPickupLocation === 'function') {
            registry.setPickupLocation(newLat, newLng);
        } else {
            latInput.value = Number(newLat).toFixed(7);
            lngInput.value = Number(newLng).toFixed(7);
        }
        if (!skipSync) {
            syncRoutePathFields();
        }
    }

    function clearSuggestionMarkers() {
        suggestionMarkers.forEach(function (marker) {
            if (mapProvider === 'google') {
                marker.setMap(null);
            } else {
                map.removeLayer(marker);
            }
        });
        suggestionMarkers = [];
    }

    window.tripMapSetSuggestedMarkers = function (areas) {
        clearSuggestionMarkers();
        if (!map) {
            return;
        }

        (areas || []).forEach(function (row) {
            if (row.latitude == null || row.longitude == null) {
                return;
            }
            const lat = parseFloat(row.latitude);
            const lng = parseFloat(row.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            let marker;
            if (mapProvider === 'google') {
                marker = new google.maps.Marker({
                    map: map,
                    position: { lat: lat, lng: lng },
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 6,
                        fillColor: '#ea580c',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                    },
                    title: row.label || row.start_label || '',
                    zIndex: 3,
                });
                marker.addListener('click', function () {
                    setStartLocation(lat, lng, true);
                    addressInput.value = row.start_label || row.label || '';
                    syncRoutePathFields();
                });
            } else {
                marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'trip-map-marker--suggestion',
                        iconSize: [12, 12],
                        iconAnchor: [6, 6],
                    }),
                    title: row.label || row.start_label || '',
                }).addTo(map);
                marker.on('click', function () {
                    setStartLocation(lat, lng, true);
                    addressInput.value = row.start_label || row.label || '';
                    syncRoutePathFields();
                });
            }

            suggestionMarkers.push(marker);
        });
    };

    window.tripMapSetStart = function (newLat, newLng, address, skipReverse) {
        if (newLat == null || newLng == null) {
            return;
        }
        setStartLocation(parseFloat(newLat), parseFloat(newLng), true);
        if (address != null && String(address).trim() !== '') {
            addressInput.value = String(address);
            syncRoutePathFields();
            return;
        }
        syncRoutePathFields();
    };

    window.tripMapClearSuggestions = clearSuggestionMarkers;

    window.tripMapSyncSchool = function () {
        syncSchoolMarker();
        syncRoutePathFields();
    };

    window.tripMapSetReturnPath = function (schoolLat, schoolLng, endLat, endLng, schoolAddress, endAddressLabel) {
        updateReturnTripMode();
        returnEndLat = parseCoord(endLat);
        returnEndLng = parseCoord(endLng);
        returnEndAddress = endAddressLabel != null ? String(endAddressLabel).trim() : '';

        if (schoolLat != null && schoolLng != null) {
            window.tripMapSetStart(schoolLat, schoolLng, schoolAddress || '', true);
        }

        if (locationInput && schoolAddress && endAddressLabel) {
            locationInput.value = returnLocationFromStartAndEnd(
                String(schoolAddress).trim(),
                String(endAddressLabel).trim(),
            );
        }

        syncRoutePathFields();
    };

    window.tripMapClearReturnEnd = function () {
        returnEndLat = null;
        returnEndLng = null;
        returnEndAddress = '';
        syncEndMarker();
        drawRouteLine();
    };

    window.tripMapSyncRoutePath = syncRoutePathFields;
    window.tripMapOnStartLocationPicked = function () {
        syncRoutePathFields();
    };

    function syncReturnTripLabels() {
        const isReturn = isReturnTripType();
        const startLabel = document.getElementById('trip_form_start_map_label');
        const pathLabel = document.getElementById('trip_form_route_path_label');
        const pathInput = document.getElementById('trip_form_location');
        const pickupPathLabel = @json(__('dashboard.trip_route_path'));
        const returnPathLabel = @json(__('dashboard.trip_route_path_return'));
        const pickupStartLabel = @json(__('dashboard.trip_start_on_map'));
        const returnStartLabel = @json(__('dashboard.trip_start_on_map_return'));

        if (startLabel) {
            startLabel.textContent = isReturn ? returnStartLabel : pickupStartLabel;
        }
        if (pathLabel) {
            pathLabel.textContent = isReturn ? returnPathLabel : pickupPathLabel;
        }
        if (pathInput) {
            pathInput.placeholder = isReturn
                ? @json(__('dashboard.trip_route_path_return_placeholder'))
                : @json(__('dashboard.trip_route_path_placeholder'));
        }
    }

    function applyInitialReturnPath() {
        const seed = window.tripMapInitialReturnPath;
        if (!seed || typeof window.tripMapSetReturnPath !== 'function') {
            return;
        }

        if (
            seed.route_start_latitude == null
            || seed.route_start_longitude == null
            || seed.route_end_latitude == null
            || seed.route_end_longitude == null
        ) {
            return;
        }

        window.tripMapSetReturnPath(
            seed.route_start_latitude,
            seed.route_start_longitude,
            seed.route_end_latitude,
            seed.route_end_longitude,
            seed.start_address || '',
            seed.end_address || '',
        );

        if (locationInput && seed.location) {
            locationInput.value = String(seed.location);
        }

        if (distanceKmInput && seed.distance_km != null && !isNaN(parseFloat(seed.distance_km))) {
            distanceKmInput.value = String(Number(parseFloat(seed.distance_km).toFixed(2)));
        }
    }

    function coordsValid(lat, lng) {
        return lat !== null && lng !== null
            && lat >= -90 && lat <= 90
            && lng >= -180 && lng <= 180;
    }

    function applyCoordsFromInputs() {
        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        if (!coordsValid(startLat, startLng)) {
            return;
        }
        setStartLocation(startLat, startLng, true);
        syncRoutePathFields();
    }

    let coordInputTimer = null;

    function onCoordInput() {
        clearTimeout(coordInputTimer);
        coordInputTimer = setTimeout(applyCoordsFromInputs, 400);
    }

    function bindCoordInputs() {
        latInput.addEventListener('input', onCoordInput);
        lngInput.addEventListener('input', onCoordInput);
        latInput.addEventListener('change', applyCoordsFromInputs);
        lngInput.addEventListener('change', applyCoordsFromInputs);
    }

    function initTripOverlays() {
        registry = getRegistry();
        if (!registry || typeof registry.getMap !== 'function') {
            setTimeout(initTripOverlays, 50);
            return;
        }

        map = registry.getMap();
        mapProvider = registry.mapProvider || 'leaflet';

        bindCoordInputs();
        updateReturnTripMode();
        syncReturnTripLabels();
        applyInitialReturnPath();

        if (schoolSelect) {
            schoolSelect.addEventListener('change', window.tripMapSyncSchool);
        }

        if (tripTypeSelect) {
            tripTypeSelect.addEventListener('change', function () {
                updateReturnTripMode();
                syncReturnTripLabels();
                if (!returnTripMode) {
                    window.tripMapClearReturnEnd();
                }
                syncRoutePathFields();
            });
        }

        syncSchoolMarker();
        if (!returnTripMode || returnEndLat === null) {
            syncRoutePathFields();
        }

        setTimeout(function () {
            if (mapProvider === 'google') {
                google.maps.event.trigger(map, 'resize');
            } else if (typeof map.invalidateSize === 'function') {
                map.invalidateSize();
            }
            fitMapBounds();
        }, 150);

        const mapEl = document.getElementById('trip_form_start_map');
        if (mapHelp && mapEl && mapEl.parentNode) {
            const hint = document.createElement('p');
            hint.className = 'help';
            hint.style.marginTop = '8px';
            hint.textContent = mapHelp;
            mapEl.parentNode.appendChild(hint);
        }
    }

    initTripOverlays();
})();
</script>
