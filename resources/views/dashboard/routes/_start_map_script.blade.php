<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>
<style>
    .route-map-marker--school {
        width: 14px;
        height: 14px;
        margin-left: -7px;
        margin-top: -7px;
        border-radius: 50%;
        background: #16a34a;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
    }
</style>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
(function () {
    const mapEl = document.getElementById('route_start_map');
    const latInput = document.getElementById('start_latitude');
    const lngInput = document.getElementById('start_longitude');
    const addressInput = document.getElementById('start_address');
    const endAddressInput = document.getElementById('end_address');
    const endLatInput = document.getElementById('end_latitude');
    const endLngInput = document.getElementById('end_longitude');
    const schoolSelect = document.getElementById('route_form_school_id');
    const reverseUrl = @json(route('dashboard.geocode.reverse'));
    const addressLoadingLabel = @json(__('dashboard.school_map_address_loading'));
    const mapHelp = @json(__('dashboard.route_map_help'));
    const endMissingLabel = @json(__('dashboard.route_end_address_missing'));

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
    let routeLine = null;

    const setStartLocation = (newLat, newLng) => {
        startMarker.setLatLng([newLat, newLng]);
        latInput.value = Number(newLat).toFixed(7);
        lngInput.value = Number(newLng).toFixed(7);
        drawRouteLine();
    };

    function parseCoord(value) {
        if (value === '' || value === null || value === undefined) {
            return null;
        }
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : null;
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

    function setSchoolEndpoint(address, latitude, longitude) {
        if (endAddressInput) {
            endAddressInput.value = address || '';
            if (!address && endMissingLabel) {
                endAddressInput.placeholder = endMissingLabel;
            }
        }
        if (endLatInput) {
            endLatInput.value = latitude !== null ? Number(latitude).toFixed(7) : '';
        }
        if (endLngInput) {
            endLngInput.value = longitude !== null ? Number(longitude).toFixed(7) : '';
        }

        if (schoolMarker) {
            map.removeLayer(schoolMarker);
            schoolMarker = null;
        }

        if (latitude !== null && longitude !== null) {
            schoolMarker = L.marker([latitude, longitude], {
                icon: L.divIcon({
                    className: 'route-map-marker--school',
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                }),
                interactive: false,
            }).addTo(map);
        }

        drawRouteLine();
        fitMapBounds();
    }

    function drawRouteLine() {
        if (routeLine) {
            map.removeLayer(routeLine);
            routeLine = null;
        }

        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        const endLat = endLatInput ? parseCoord(endLatInput.value) : null;
        const endLng = endLngInput ? parseCoord(endLngInput.value) : null;

        if (startLat === null || startLng === null || endLat === null || endLng === null) {
            return;
        }

        routeLine = L.polyline(
            [[startLat, startLng], [endLat, endLng]],
            { color: '#2563eb', weight: 3, opacity: 0.75, dashArray: '6,8' },
        ).addTo(map);
    }

    function fitMapBounds() {
        const points = [];
        const startLat = parseCoord(latInput.value);
        const startLng = parseCoord(lngInput.value);
        const endLat = endLatInput ? parseCoord(endLatInput.value) : null;
        const endLng = endLngInput ? parseCoord(endLngInput.value) : null;

        if (startLat !== null && startLng !== null) {
            points.push([startLat, startLng]);
        }
        if (endLat !== null && endLng !== null) {
            points.push([endLat, endLng]);
        }

        if (points.length >= 2) {
            map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
        } else if (points.length === 1) {
            map.setView(points[0], 13);
        }
    }

    window.syncRouteSchoolEndpoint = function () {
        const data = selectedSchoolData();
        setSchoolEndpoint(data.address, data.latitude, data.longitude);
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
                return;
            }
            const json = await res.json();
            const data = json.data || {};
            addressInput.value = data.address || previousAddress;
        } catch (e) {
            if (requestId === reverseRequestId) {
                addressInput.value = previousAddress;
            }
            console.error(e);
        } finally {
            if (requestId === reverseRequestId) {
                addressInput.disabled = false;
            }
        }
    }

    map.on('click', function (event) {
        setStartLocation(event.latlng.lat, event.latlng.lng);
        fillAddressFromMap(event.latlng.lat, event.latlng.lng);
    });

    if (schoolSelect) {
        schoolSelect.addEventListener('change', window.syncRouteSchoolEndpoint);
    }

    window.syncRouteSchoolEndpoint();
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
