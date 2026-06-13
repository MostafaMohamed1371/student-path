<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>
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
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
(function () {
    const mapEl = document.getElementById('trip_form_start_map');
    const latInput = document.getElementById('trip_form_start_latitude');
    const lngInput = document.getElementById('trip_form_start_longitude');
    const addressInput = document.getElementById('trip_form_start_address');
    const locationInput = document.getElementById('trip_form_location');
    const distanceKmInput = document.getElementById('trip_form_distance_km');
    const schoolSelect = document.getElementById('trip_form_school_id');
    const reverseUrl = @json(route('dashboard.geocode.reverse'));
    const addressLoadingLabel = @json(__('dashboard.school_map_address_loading'));
    const mapHelp = @json(__('dashboard.trip_map_help'));
    const locationStartToEnd = @json(__('dashboard.trip_location_start_to_end', ['start' => '__START__', 'end' => '__END__']));
    const locationStartOnly = @json(__('dashboard.trip_location_start_only', ['start' => '__START__']));
    const locationEndOnly = @json(__('dashboard.trip_location_end_only', ['end' => '__END__']));

    if (!mapEl || !latInput || !lngInput || !addressInput || typeof L === 'undefined') {
        return;
    }

    const defaultLat = 33.3128;
    const defaultLng = 44.3615;
    const hasValues = latInput.value !== '' && lngInput.value !== '';
    const lat = hasValues ? parseFloat(latInput.value) : defaultLat;
    const lng = hasValues ? parseFloat(lngInput.value) : defaultLng;

    const map = L.map(mapEl).setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    let startMarker = L.marker([lat, lng]).addTo(map);
    let schoolMarker = null;
    let endMarker = null;
    let routeLine = null;
    let suggestionMarkers = [];
    let returnEndLat = null;
    let returnEndLng = null;
    let returnTripMode = false;

    const tripTypeSelect = document.getElementById('trip_form_trip_type');

    function isReturnTripType() {
        const value = tripTypeSelect ? String(tripTypeSelect.value || '') : '';

        return value.endsWith('_RETURN');
    }

    function updateReturnTripMode() {
        returnTripMode = isReturnTripType();
    }

    function parseCoord(value) {
        if (value === '' || value === null || value === undefined) {
            return null;
        }
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : null;
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

    function drawRouteLine() {
        if (routeLine) {
            map.removeLayer(routeLine);
            routeLine = null;
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
            routeLine = L.polyline(
                [[lineStartLat, lineStartLng], [returnEndLat, returnEndLng]],
                { color: '#2563eb', weight: 3, opacity: 0.75, dashArray: '6,8' },
            ).addTo(map);

            return;
        }

        if (startLat === null || startLng === null || school.latitude === null || school.longitude === null) {
            return;
        }

        routeLine = L.polyline(
            [[startLat, startLng], [school.latitude, school.longitude]],
            { color: '#2563eb', weight: 3, opacity: 0.75, dashArray: '6,8' },
        ).addTo(map);
    }

    function syncEndMarker() {
        if (endMarker) {
            map.removeLayer(endMarker);
            endMarker = null;
        }

        if (!returnTripMode || returnEndLat === null || returnEndLng === null) {
            return;
        }

        endMarker = L.marker([returnEndLat, returnEndLng]).addTo(map);
    }

    function fitMapBounds() {
        const points = [];
        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        const school = selectedSchoolData();

        if (startLat !== null && startLng !== null) {
            points.push([startLat, startLng]);
        }
        if (school.latitude !== null && school.longitude !== null) {
            points.push([school.latitude, school.longitude]);
        }
        if (returnTripMode && returnEndLat !== null && returnEndLng !== null) {
            points.push([returnEndLat, returnEndLng]);
        }

        if (points.length >= 2) {
            map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
        } else if (points.length === 1) {
            map.setView(points[0], 13);
        }
    }

    function syncSchoolMarker() {
        const school = selectedSchoolData();

        if (schoolMarker) {
            map.removeLayer(schoolMarker);
            schoolMarker = null;
        }

        if (school.latitude !== null && school.longitude !== null) {
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
        const end = school.address != null ? String(school.address).trim() : '';

        if (locationInput) {
            locationInput.value = locationFromStartAndEnd(start, end);
        }

        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        if (distanceKmInput && startLat !== null && startLng !== null) {
            if (returnTripMode && returnEndLat !== null && returnEndLng !== null) {
                distanceKmInput.value = String(haversineKm(startLat, startLng, returnEndLat, returnEndLng));
            } else {
                const school = selectedSchoolData();
                if (school.latitude !== null && school.longitude !== null) {
                    distanceKmInput.value = String(haversineKm(startLat, startLng, school.latitude, school.longitude));
                }
            }
        }

        syncEndMarker();
        drawRouteLine();
        fitMapBounds();
    }

    const setStartLocation = (newLat, newLng, skipSync) => {
        startMarker.setLatLng([newLat, newLng]);
        latInput.value = Number(newLat).toFixed(7);
        lngInput.value = Number(newLng).toFixed(7);
        if (!skipSync) {
            syncRoutePathFields();
        }
    };

    let reverseRequestId = 0;

    async function fillAddressFromMap(lat, lng) {
        const requestId = ++reverseRequestId;
        const previousAddress = addressInput.value;
        addressInput.value = addressLoadingLabel;
        addressInput.disabled = true;

        try {
            const params = new URLSearchParams({ latitude: String(lat), longitude: String(lng) });
            const res = await fetch(reverseUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (requestId !== reverseRequestId) {
                return;
            }
            if (!res.ok) {
                addressInput.value = previousAddress;
                syncRoutePathFields();
                return;
            }
            const json = await res.json();
            const data = json.data || {};
            addressInput.value = data.address || previousAddress;
            syncRoutePathFields();
        } catch (e) {
            if (requestId === reverseRequestId) {
                addressInput.value = previousAddress;
                syncRoutePathFields();
            }
            console.error(e);
        } finally {
            if (requestId === reverseRequestId) {
                addressInput.disabled = false;
            }
        }
    }

    function clearSuggestionMarkers() {
        suggestionMarkers.forEach(function (marker) {
            map.removeLayer(marker);
        });
        suggestionMarkers = [];
    }

    window.tripMapSetSuggestedMarkers = function (areas) {
        clearSuggestionMarkers();

        (areas || []).forEach(function (row) {
            if (row.latitude == null || row.longitude == null) {
                return;
            }
            const lat = parseFloat(row.latitude);
            const lng = parseFloat(row.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            const marker = L.marker([lat, lng], {
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
        if (skipReverse) {
            syncRoutePathFields();
            return;
        }
        fillAddressFromMap(parseFloat(newLat), parseFloat(newLng));
    };

    window.tripMapClearSuggestions = function () {
        clearSuggestionMarkers();
    };

    window.tripMapSyncSchool = function () {
        syncSchoolMarker();
        syncRoutePathFields();
    };

    window.tripMapSetReturnPath = function (schoolLat, schoolLng, endLat, endLng, schoolAddress, endAddressLabel) {
        updateReturnTripMode();
        returnEndLat = parseCoord(endLat);
        returnEndLng = parseCoord(endLng);

        if (schoolLat != null && schoolLng != null && typeof window.tripMapSetStart === 'function') {
            window.tripMapSetStart(schoolLat, schoolLng, schoolAddress || '', true);
        }

        if (locationInput && schoolAddress && endAddressLabel) {
            locationInput.value = locationFromStartAndEnd(
                String(schoolAddress).trim(),
                String(endAddressLabel).trim(),
            );
        }

        syncRoutePathFields();
    };

    window.tripMapClearReturnEnd = function () {
        returnEndLat = null;
        returnEndLng = null;
        syncEndMarker();
        drawRouteLine();
    };

    window.tripMapSyncRoutePath = syncRoutePathFields;

    function coordsValid(lat, lng) {
        return lat !== null && lng !== null
            && lat >= -90 && lat <= 90
            && lng >= -180 && lng <= 180;
    }

    function applyCoordsFromInputs(reverseGeocode) {
        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        if (!coordsValid(startLat, startLng)) {
            return;
        }

        startMarker.setLatLng([startLat, startLng]);
        latInput.value = Number(startLat).toFixed(7);
        lngInput.value = Number(startLng).toFixed(7);

        if (reverseGeocode) {
            fillAddressFromMap(startLat, startLng);
        } else {
            syncRoutePathFields();
        }
    }

    let coordInputTimer = null;

    function onCoordInput() {
        clearTimeout(coordInputTimer);
        coordInputTimer = setTimeout(function () {
            applyCoordsFromInputs(false);
        }, 400);
    }

    latInput.addEventListener('input', onCoordInput);
    lngInput.addEventListener('input', onCoordInput);
    latInput.addEventListener('change', function () {
        applyCoordsFromInputs(true);
    });
    lngInput.addEventListener('change', function () {
        applyCoordsFromInputs(true);
    });

    map.on('click', function (event) {
        if (returnTripMode) {
            return;
        }
        setStartLocation(event.latlng.lat, event.latlng.lng, true);
        fillAddressFromMap(event.latlng.lat, event.latlng.lng);
    });

    if (tripTypeSelect) {
        tripTypeSelect.addEventListener('change', function () {
            updateReturnTripMode();
            if (!returnTripMode && typeof window.tripMapClearReturnEnd === 'function') {
                window.tripMapClearReturnEnd();
            }
            syncRoutePathFields();
        });
    }

    updateReturnTripMode();

    if (schoolSelect) {
        schoolSelect.addEventListener('change', window.tripMapSyncSchool);
    }

    syncSchoolMarker();
    syncRoutePathFields();
    setTimeout(function () { map.invalidateSize(); fitMapBounds(); }, 100);

    if (mapHelp) {
        const hint = document.createElement('p');
        hint.className = 'help';
        hint.style.marginTop = '8px';
        hint.textContent = mapHelp;
        mapEl.parentNode.appendChild(hint);
    }
})();
</script>
