<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
(function () {
    const mapEl = document.getElementById('guardian-home-map');
    const latInput = document.getElementById('guardian_home_latitude');
    const lngInput = document.getElementById('guardian_home_longitude');
    const landmarkInput = document.getElementById('guardian_home_nearest_landmark');
    const formattedInput = document.getElementById('guardian_home_formatted_address');
    const reverseUrl = @json(route('dashboard.geocode.reverse'));
    const resolveUrl = @json(route('dashboard.locations.resolve_neighborhood'));
    const addressLoadingLabel = @json(__('dashboard.school_map_address_loading'));
    const locationPrefix = 'guardian_home';

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

    let marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    let neighborhoodMarkers = [];

    const setLocation = (newLat, newLng) => {
        marker.setLatLng([newLat, newLng]);
        latInput.value = Number(newLat).toFixed(7);
        lngInput.value = Number(newLng).toFixed(7);
    };

    function setLandmarkValue(value) {
        if (landmarkInput) {
            landmarkInput.value = value;
        }
        if (formattedInput) {
            formattedInput.value = value;
        }
    }

    let reverseRequestId = 0;
    let resolveRequestId = 0;

    async function fillAddressFromMap(lat, lng) {
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
        } catch (e) {
            setLandmarkValue(previousLandmark);
        } finally {
            if (requestId === reverseRequestId) {
                landmarkInput.disabled = false;
            }
        }
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

        if (!data.neighborhood) {
            document.dispatchEvent(new CustomEvent('iraq-location-map-not-found', {
                detail: { prefix: locationPrefix },
            }));
            return null;
        }

        if (typeof window.setIraqLocationCascadeValues === 'function') {
            await window.setIraqLocationCascadeValues(locationPrefix, {
                home_district_id: data.neighborhood.district_id,
                home_area_id: data.neighborhood.area_id,
                home_neighborhood_id: data.neighborhood.id,
            });
        }

        return data.neighborhood;
    }

    if (landmarkInput) {
        landmarkInput.addEventListener('input', function () {
            const value = landmarkInput.value;
            if (formattedInput) {
                formattedInput.value = value;
            }
        });
    }

    function onLocationPicked(newLat, newLng) {
        setLocation(newLat, newLng);
        fillAddressFromMap(newLat, newLng);
        applyNeighborhoodFromMap(newLat, newLng);
    }

    map.on('click', function (event) {
        onLocationPicked(event.latlng.lat, event.latlng.lng);
    });

    marker.on('dragend', function () {
        const pos = marker.getLatLng();
        onLocationPicked(pos.lat, pos.lng);
    });

    function clearNeighborhoodMarkers() {
        neighborhoodMarkers.forEach(function (mapMarker) {
            map.removeLayer(mapMarker);
        });
        neighborhoodMarkers = [];
    }

    function showNeighborhoodMarkers(neighborhoods, selectedNeighborhoodId, options) {
        options = options || {};
        clearNeighborhoodMarkers();

        const bounds = [];
        const selectedId = String(selectedNeighborhoodId || '');

        (neighborhoods || []).forEach(function (item) {
            const itemLat = parseFloat(item.latitude);
            const itemLng = parseFloat(item.longitude);
            if (!Number.isFinite(itemLat) || !Number.isFinite(itemLng)) {
                return;
            }

            const mapMarker = L.marker([itemLat, itemLng], {
                icon: L.divIcon({
                    className: 'iraq-map-marker--neighborhood' + (selectedId === String(item.id) ? ' is-selected' : ''),
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                }),
                title: item.name,
                interactive: false,
            }).addTo(map);

            mapMarker.neighborhoodPayload = item;

            neighborhoodMarkers.push(mapMarker);
            bounds.push([itemLat, itemLng]);
        });

        const hasPinnedLocation = latInput.value !== '' && lngInput.value !== '';
        const shouldFitMap = options.fitMap === true
            || (bounds.length > 0 && !selectedId && !hasPinnedLocation);

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
    window.IraqLocationMapRegistry.guardian_home = {
        clearNeighborhoodMarkers: clearNeighborhoodMarkers,
        showNeighborhoodMarkers: showNeighborhoodMarkers,
        focusNeighborhood: focusNeighborhood,
        setPickupLocation: setLocation,
    };

    window.guardianHomeMapSetLocation = function (newLat, newLng, landmark, district) {
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
        if (typeof landmark === 'string' && landmark.trim() !== '') {
            setLandmarkValue(landmark);
        } else {
            fillAddressFromMap(parsedLat, parsedLng);
        }
        applyNeighborhoodFromMap(parsedLat, parsedLng);
    };

    setTimeout(function () {
        map.invalidateSize();
    }, 120);
})();
</script>
