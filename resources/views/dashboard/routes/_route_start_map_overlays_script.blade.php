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
<script>
(function () {
    const registryKey = 'route';
    const latInput = document.getElementById('start_latitude');
    const lngInput = document.getElementById('start_longitude');
    const endAddressInput = document.getElementById('end_address');
    const endLatInput = document.getElementById('end_latitude');
    const endLngInput = document.getElementById('end_longitude');
    const schoolSelect = document.getElementById('route_form_school_id');
    const mapHelp = @json(__('dashboard.route_map_help'));
    const endMissingLabel = @json(__('dashboard.route_end_address_missing'));

    if (!latInput || !lngInput) {
        return;
    }

    let map = null;
    let mapProvider = 'leaflet';
    let schoolMarker = null;
    let routeLine = null;

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
        const endLat = endLatInput ? parseCoord(endLatInput.value) : null;
        const endLng = endLngInput ? parseCoord(endLngInput.value) : null;

        if (startLat === null || startLng === null || endLat === null || endLng === null) {
            return;
        }

        if (mapProvider === 'google') {
            routeLine = new google.maps.Polyline({
                map: map,
                path: [
                    { lat: startLat, lng: startLng },
                    { lat: endLat, lng: endLng },
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
                [[startLat, startLng], [endLat, endLng]],
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
        const endLat = endLatInput ? parseCoord(endLatInput.value) : null;
        const endLng = endLngInput ? parseCoord(endLngInput.value) : null;
        const points = [];

        if (startLat !== null && startLng !== null) {
            points.push({ lat: startLat, lng: startLng });
        }
        if (endLat !== null && endLng !== null) {
            points.push({ lat: endLat, lng: endLng });
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

        removeSchoolMarker();
        if (!map || latitude === null || longitude === null) {
            drawRouteLine();
            fitMapBounds();
            return;
        }

        if (mapProvider === 'google') {
            schoolMarker = new google.maps.Marker({
                map: map,
                position: { lat: latitude, lng: longitude },
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

    window.syncRouteSchoolEndpoint = function () {
        const data = selectedSchoolData();
        setSchoolEndpoint(data.address, data.latitude, data.longitude);
    };

    window.routeMapOnStartLocationPicked = function () {
        drawRouteLine();
        fitMapBounds();
    };

    function bindCoordInputs() {
        latInput.addEventListener('input', window.routeMapOnStartLocationPicked);
        lngInput.addEventListener('input', window.routeMapOnStartLocationPicked);
        latInput.addEventListener('change', window.routeMapOnStartLocationPicked);
        lngInput.addEventListener('change', window.routeMapOnStartLocationPicked);
    }

    function initRouteOverlays() {
        const registry = getRegistry();
        if (!registry || typeof registry.getMap !== 'function') {
            setTimeout(initRouteOverlays, 50);
            return;
        }

        map = registry.getMap();
        mapProvider = registry.mapProvider || 'leaflet';

        bindCoordInputs();

        if (schoolSelect) {
            schoolSelect.addEventListener('change', window.syncRouteSchoolEndpoint);
        }

        window.syncRouteSchoolEndpoint();

        setTimeout(function () {
            if (mapProvider === 'google') {
                google.maps.event.trigger(map, 'resize');
            } else if (typeof map.invalidateSize === 'function') {
                map.invalidateSize();
            }
            fitMapBounds();
        }, 150);

        const mapEl = document.getElementById('route_start_map');
        if (mapHelp && mapEl && mapEl.parentNode) {
            const hint = document.createElement('p');
            hint.className = 'help';
            hint.style.marginTop = '8px';
            hint.textContent = mapHelp;
            mapEl.parentNode.appendChild(hint);
        }
    }

    initRouteOverlays();
})();
</script>
