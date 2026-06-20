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
    $googleMapsApiKey = (string) config('google.maps_api_key');
@endphp
@include('dashboard.partials.google_maps_loader')
@if($googleMapsApiKey === '')
<p style="margin:0 0 12px;font-size:12px;color:#b45309;">{{ __('dashboard.google_maps_api_key_missing') }}</p>
@else
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

    if (!mapEl || !latInput || !lngInput || typeof window.ensureGoogleMapsLoaded !== 'function') {
        return;
    }

    window.ensureGoogleMapsLoaded(function () {
        if (!window.google || !window.google.maps) {
            return;
        }

        const defaultLat = 33.3128;
        const defaultLng = 44.3615;
        const hasValues = latInput.value !== '' && lngInput.value !== '';
        const lat = hasValues ? parseFloat(latInput.value) : defaultLat;
        const lng = hasValues ? parseFloat(lngInput.value) : defaultLng;

        const map = new google.maps.Map(mapEl, {
            center: { lat: lat, lng: lng },
            zoom: hasValues ? 14 : 12,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true,
        });

        let pickupMarker = new google.maps.Marker({
            map: map,
            position: { lat: lat, lng: lng },
            draggable: true,
            title: @json(__('dashboard.latitude')),
        });

        let neighborhoodMarkers = [];
        let reverseRequestId = 0;
        let resolveRequestId = 0;

        function neighborhoodIcon(isSelected) {
            return {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 7,
                fillColor: isSelected ? '#16a34a' : '#2563eb',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
            };
        }

        function setLandmarkValue(value) {
            if (landmarkInput) {
                landmarkInput.value = value;
            }
            if (formattedInput) {
                formattedInput.value = value;
            }
        }

        function setLocation(newLat, newLng) {
            pickupMarker.setPosition({ lat: newLat, lng: newLng });
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
                const params = new URLSearchParams({
                    latitude: String(lat),
                    longitude: String(lng),
                });
                const res = await fetch(reverseUrl + '?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
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
                if (data.address) {
                    setLandmarkValue(data.address);
                } else {
                    setLandmarkValue(previousLandmark);
                }
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
            const params = new URLSearchParams({
                latitude: String(lat),
                longitude: String(lng),
            });

            const res = await fetch(resolveUrl + '?' + params.toString(), {
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

        map.addListener('click', function (event) {
            if (!event.latLng) {
                return;
            }
            onLocationPicked(event.latLng.lat(), event.latLng.lng());
        });

        pickupMarker.addListener('dragend', function () {
            const position = pickupMarker.getPosition();
            if (!position) {
                return;
            }
            onLocationPicked(position.lat(), position.lng());
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
                marker.setMap(null);
            });
            neighborhoodMarkers = [];
        }

        function showNeighborhoodMarkers(neighborhoods, selectedNeighborhoodId, options) {
            options = options || {};
            clearNeighborhoodMarkers();

            const bounds = new google.maps.LatLngBounds();
            const selectedId = String(selectedNeighborhoodId || '');
            const hasSelected = selectedId !== ''
                || (Array.isArray(options.selectedNeighborhoodIds) && options.selectedNeighborhoodIds.length > 0);

            (neighborhoods || []).forEach(function (item) {
                const itemLat = parseFloat(item.latitude);
                const itemLng = parseFloat(item.longitude);
                if (!Number.isFinite(itemLat) || !Number.isFinite(itemLng)) {
                    return;
                }

                const position = { lat: itemLat, lng: itemLng };
                const mapMarker = new google.maps.Marker({
                    map: map,
                    position: position,
                    icon: neighborhoodIcon(isNeighborhoodSelected(item.id, selectedId, options)),
                    title: item.name || '',
                    clickable: false,
                    zIndex: 1,
                });

                mapMarker.neighborhoodPayload = item;
                neighborhoodMarkers.push(mapMarker);
                bounds.extend(position);
            });

            const hasPinnedLocation = latInput.value !== '' && lngInput.value !== '';
            const shouldFitMap = options.fitMap === true
                || (neighborhoodMarkers.length > 0 && !hasSelected && !hasPinnedLocation);

            if (shouldFitMap && neighborhoodMarkers.length > 0) {
                google.maps.event.trigger(map, 'resize');
                map.fitBounds(bounds, 36);
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
                map.panTo({ lat: itemLat, lng: itemLng });
                map.setZoom(15);
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
                map.panTo({ lat: parsedLat, lng: parsedLng });
                map.setZoom(14);

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
            google.maps.event.trigger(map, 'resize');
            map.setCenter(pickupMarker.getPosition() || { lat: lat, lng: lng });
        }, 120);
    });
})();
</script>
@endif
