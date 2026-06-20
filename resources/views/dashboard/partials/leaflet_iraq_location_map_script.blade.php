@php
    $mapElementId = $mapElementId ?? 'student-location-map';
    $latInputId = $latInputId ?? 'latitude';
    $lngInputId = $lngInputId ?? 'longitude';
    $landmarkInputId = $landmarkInputId ?? null;
    $formattedInputId = $formattedInputId ?? null;
    $locationPrefix = $locationPrefix ?? 'student';
    $registryKey = $registryKey ?? $locationPrefix;
    $globalSetLocationFn = $globalSetLocationFn ?? null;
    $locationMode = $locationMode ?? 'form';
@endphp
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const mapElementId = @json($mapElementId);
    const latInputId = @json($latInputId);
    const lngInputId = @json($lngInputId);
    const landmarkInputId = @json($landmarkInputId);
    const formattedInputId = @json($formattedInputId);
    const locationPrefix = @json($locationPrefix);
    const registryKey = @json($registryKey);
    const globalSetLocationFn = @json($globalSetLocationFn);
    const locationMode = @json($locationMode);
    const reverseUrl = @json(route('dashboard.geocode.reverse'));
    const resolveUrl = @json(route('dashboard.locations.resolve_neighborhood'));
    const addressLoadingLabel = @json(__('dashboard.school_map_address_loading'));
    const cascadeFieldPrefix = locationPrefix === 'guardian_home' ? 'home_' : '';

    const mapEl = document.getElementById(mapElementId);
    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    const landmarkInput = landmarkInputId ? document.getElementById(landmarkInputId) : null;
    const formattedInput = formattedInputId ? document.getElementById(formattedInputId) : null;

    if (!mapEl || !latInput || !lngInput || typeof L === 'undefined') {
        return;
    }

    const defaultLat = 33.3128;
    const defaultLng = 44.3615;
    const hasValues = latInput.value !== '' && lngInput.value !== '';
    const lat = hasValues ? parseFloat(latInput.value) : defaultLat;
    const lng = hasValues ? parseFloat(lngInput.value) : defaultLng;

    const map = L.map(mapEl).setView([lat, lng], hasValues ? 14 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    let pickupMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
    let neighborhoodMarkers = [];
    let reverseRequestId = 0;
    let resolveRequestId = 0;

    function setLandmarkValue(value) {
        if (landmarkInput) {
            landmarkInput.value = value;
        }
        if (formattedInput) {
            formattedInput.value = value;
        }
    }

    function setLocation(newLat, newLng) {
        pickupMarker.setLatLng([newLat, newLng]);
        latInput.value = Number(newLat).toFixed(7);
        lngInput.value = Number(newLng).toFixed(7);
    }

    async function fillLandmarkFromMap(lat, lng) {
        if (!reverseUrl || !landmarkInput) {
            return;
        }

        const requestId = ++reverseRequestId;
        const previousLandmark = landmarkInput.value;
        setLandmarkValue(addressLoadingLabel);
        landmarkInput.disabled = true;

        try {
            const params = new URLSearchParams({ latitude: String(lat), longitude: String(lng) });
            const res = await fetch(reverseUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (requestId !== reverseRequestId) {
                return;
            }
            if (!res.ok) {
                setLandmarkValue(previousLandmark);
                return;
            }
            const json = await res.json();
            const data = json.data || {};
            setLandmarkValue(data.address || previousLandmark);
        } catch (error) {
            setLandmarkValue(previousLandmark);
        } finally {
            if (requestId === reverseRequestId) {
                landmarkInput.disabled = false;
            }
        }
    }

    function isNeighborhoodSelected(itemId, selectedNeighborhoodId, options) {
        const selectedIds = options.selectedNeighborhoodIds;
        if (Array.isArray(selectedIds) && selectedIds.length > 0) {
            return selectedIds.map(String).includes(String(itemId));
        }

        return String(selectedNeighborhoodId || '') === String(itemId);
    }

    async function applyNeighborhoodFromMap(lat, lng) {
        const requestId = ++resolveRequestId;
        const res = await fetch(resolveUrl + '?' + new URLSearchParams({
            latitude: String(lat),
            longitude: String(lng),
        }).toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { neighborhood: null };
        if (requestId !== resolveRequestId) {
            return null;
        }

        if (locationMode === 'driver') {
            const row = window.DriverServiceAreas ? window.DriverServiceAreas.getActiveRow() : null;
            if (!row) {
                document.dispatchEvent(new CustomEvent('iraq-location-map-not-found', {
                    detail: { prefix: locationPrefix, reason: 'no_row' },
                }));
                return null;
            }
            if (!data.neighborhood) {
                document.dispatchEvent(new CustomEvent('iraq-location-map-not-found', {
                    detail: { prefix: locationPrefix },
                }));
                return null;
            }
            if (typeof window.DriverServiceAreas.applyNeighborhoodSelection === 'function') {
                await window.DriverServiceAreas.applyNeighborhoodSelection(row, {
                    district_id: data.neighborhood.district_id,
                    area_id: data.neighborhood.area_id,
                    neighborhood_id: data.neighborhood.id,
                });
                document.dispatchEvent(new CustomEvent('driver-service-area-updated'));
            }
            return data.neighborhood;
        }

        if (!data.neighborhood) {
            document.dispatchEvent(new CustomEvent('iraq-location-map-not-found', {
                detail: { prefix: locationPrefix },
            }));
            return null;
        }
        if (typeof window.setIraqLocationCascadeValues === 'function') {
            const payload = {
                district_id: data.neighborhood.district_id,
                area_id: data.neighborhood.area_id,
                neighborhood_id: data.neighborhood.id,
            };
            if (cascadeFieldPrefix !== '') {
                payload[cascadeFieldPrefix + 'district_id'] = data.neighborhood.district_id;
                payload[cascadeFieldPrefix + 'area_id'] = data.neighborhood.area_id;
                payload[cascadeFieldPrefix + 'neighborhood_id'] = data.neighborhood.id;
            }
            await window.setIraqLocationCascadeValues(locationPrefix, payload);
        }
        return data.neighborhood;
    }

    function onLocationPicked(newLat, newLng, skipLandmark) {
        setLocation(newLat, newLng);
        if (!skipLandmark) {
            fillLandmarkFromMap(newLat, newLng);
        }
        applyNeighborhoodFromMap(newLat, newLng);
    }

    map.on('click', function (event) {
        onLocationPicked(event.latlng.lat, event.latlng.lng);
    });
    pickupMarker.on('dragend', function () {
        const pos = pickupMarker.getLatLng();
        onLocationPicked(pos.lat, pos.lng);
    });

    if (landmarkInput) {
        landmarkInput.addEventListener('input', function () {
            if (formattedInput) {
                formattedInput.value = landmarkInput.value;
            }
        });
    }

    function clearNeighborhoodMarkers() {
        neighborhoodMarkers.forEach(function (marker) {
            map.removeLayer(marker);
        });
        neighborhoodMarkers = [];
    }

    function showNeighborhoodMarkers(neighborhoods, selectedNeighborhoodId, options) {
        options = options || {};
        clearNeighborhoodMarkers();
        const bounds = [];
        const selectedId = String(selectedNeighborhoodId || '');
        const hasSelected = selectedId !== ''
            || (Array.isArray(options.selectedNeighborhoodIds) && options.selectedNeighborhoodIds.length > 0);

        (neighborhoods || []).forEach(function (item) {
            const itemLat = parseFloat(item.latitude);
            const itemLng = parseFloat(item.longitude);
            if (!Number.isFinite(itemLat) || !Number.isFinite(itemLng)) {
                return;
            }
            const mapMarker = L.marker([itemLat, itemLng], {
                icon: L.divIcon({
                    className: 'iraq-map-marker--neighborhood' + (isNeighborhoodSelected(item.id, selectedId, options) ? ' is-selected' : ''),
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                }),
                title: item.name,
                interactive: false,
            }).addTo(map);
            neighborhoodMarkers.push(mapMarker);
            bounds.push([itemLat, itemLng]);
        });

        const hasPinnedLocation = latInput.value !== '' && lngInput.value !== '';
        const shouldFitMap = options.fitMap === true
            || (bounds.length > 0 && !hasSelected && !hasPinnedLocation);
        if (shouldFitMap && bounds.length > 0) {
            map.invalidateSize();
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
        }
    }

    function focusNeighborhood(item, options) {
        if (!item) {
            return;
        }
        const itemLat = parseFloat(item.latitude);
        const itemLng = parseFloat(item.longitude);
        if (!Number.isFinite(itemLat) || !Number.isFinite(itemLng)) {
            return;
        }
        if (options && options.updatePickupMarker) {
            setLocation(itemLat, itemLng);
        }
        if (options && options.panMap) {
            map.setView([itemLat, itemLng], 15);
        }
    }

    window.IraqLocationMapRegistry = window.IraqLocationMapRegistry || {};
    window.IraqLocationMapRegistry[registryKey] = {
        clearNeighborhoodMarkers: clearNeighborhoodMarkers,
        showNeighborhoodMarkers: showNeighborhoodMarkers,
        focusNeighborhood: focusNeighborhood,
        setPickupLocation: setLocation,
    };

    if (globalSetLocationFn) {
        window[globalSetLocationFn] = function (newLat, newLng, landmark, district, skipReverseGeocode) {
            if (newLat === null || newLng === null || newLat === '' || newLng === '') {
                return;
            }
            const parsedLat = parseFloat(newLat);
            const parsedLng = parseFloat(newLng);
            if (Number.isNaN(parsedLat) || Number.isNaN(parsedLng)) {
                return;
            }
            setLocation(parsedLat, parsedLng);
            map.setView([parsedLat, parsedLng], 14);
            const landmarkValue = typeof landmark === 'string' ? landmark.trim() : '';
            if (landmarkInput && landmarkValue !== '') {
                setLandmarkValue(landmarkValue);
            } else if (!skipReverseGeocode) {
                fillLandmarkFromMap(parsedLat, parsedLng);
            }
            applyNeighborhoodFromMap(parsedLat, parsedLng);
        };
    }

    setTimeout(function () {
        map.invalidateSize();
    }, 120);
})();
</script>
