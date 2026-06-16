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
    const mapEl = document.getElementById('driver_service_area_map');
    const noticeEl = document.getElementById('driver_service_area_map_notice');
    const neighborhoodsUrl = @json(route('dashboard.locations.neighborhoods'));
    const resolveUrl = @json(route('dashboard.locations.resolve_neighborhood'));
    const notFoundLabel = @json(__('dashboard.driver_service_area_map_not_found'));

    if (!mapEl || typeof L === 'undefined' || !window.DriverServiceAreas) {
        return;
    }

    const defaultLat = 33.3128;
    const defaultLng = 44.3615;
    const map = L.map(mapEl).setView([defaultLat, defaultLng], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    let neighborhoodMarkers = [];
    let markerRequestId = 0;
    let resolveRequestId = 0;
    let markerNeighborhoods = [];

    function showNotice(message) {
        if (!noticeEl) {
            return;
        }
        if (!message) {
            noticeEl.style.display = 'none';
            noticeEl.textContent = '';
            return;
        }
        noticeEl.style.display = 'block';
        noticeEl.textContent = message;
        clearTimeout(showNotice._timer);
        showNotice._timer = setTimeout(function () {
            showNotice('');
        }, 3200);
    }

    function clearNeighborhoodMarkers() {
        neighborhoodMarkers.forEach(function (marker) {
            map.removeLayer(marker);
        });
        neighborhoodMarkers = [];
        markerNeighborhoods = [];
    }

    function selectedNeighborhoodIdsForRow(row) {
        const select = row ? row.querySelector('.driver-service-area-neighborhood') : null;
        if (!select) {
            return new Set();
        }

        return new Set(Array.from(select.selectedOptions).map(function (option) {
            return String(option.value);
        }).filter(function (value) {
            return value !== '';
        }));
    }

    function refreshMarkerSelectionStyles() {
        const row = window.DriverServiceAreas.getActiveRow();
        const selectedIds = selectedNeighborhoodIdsForRow(row);

        neighborhoodMarkers.forEach(function (marker) {
            const neighborhoodId = String(marker.neighborhoodId || '');
            const iconEl = marker.getElement();
            if (iconEl) {
                iconEl.classList.toggle('is-selected', selectedIds.has(neighborhoodId));
            }
        });
    }

    async function renderMarkersForActiveRow() {
        const row = window.DriverServiceAreas.getActiveRow();
        if (!row) {
            clearNeighborhoodMarkers();
            return;
        }

        const requestId = ++markerRequestId;
        const filter = window.DriverServiceAreas.getRowFilter(row);
        const params = new URLSearchParams({ with_coordinates: '1' });
        if (filter.district_id) {
            params.set('district_id', filter.district_id);
        }
        if (filter.area_id) {
            params.set('area_id', filter.area_id);
        }

        if (!filter.district_id) {
            clearNeighborhoodMarkers();
            return;
        }

        const res = await fetch(neighborhoodsUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { neighborhoods: [] };

        if (requestId !== markerRequestId) {
            return;
        }

        clearNeighborhoodMarkers();
        markerNeighborhoods = (data.neighborhoods || []).filter(function (item) {
            return item.latitude != null && item.longitude != null;
        });

        const bounds = [];
        markerNeighborhoods.forEach(function (item) {
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'driver-map-marker--neighborhood',
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                }),
                title: item.name,
            }).addTo(map);

            marker.neighborhoodId = item.id;
            marker.neighborhoodPayload = item;
            marker.on('click', function (event) {
                L.DomEvent.stopPropagation(event);
                applyNeighborhood(item);
            });

            neighborhoodMarkers.push(marker);
            bounds.push([lat, lng]);
        });

        refreshMarkerSelectionStyles();

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
        }
    }

    async function applyNeighborhood(item) {
        const row = window.DriverServiceAreas.getActiveRow();
        if (!row || !item) {
            return;
        }

        const applied = await window.DriverServiceAreas.applyNeighborhoodSelection(row, {
            neighborhood_id: item.id,
            area_id: item.area_id,
            district_id: item.district_id,
            preserve_selection: true,
        });

        if (applied) {
            showNotice('');
            refreshMarkerSelectionStyles();
        }
    }

    async function resolveFromMapClick(lat, lng) {
        const row = window.DriverServiceAreas.getActiveRow();
        if (!row) {
            return;
        }

        const requestId = ++resolveRequestId;
        const filter = window.DriverServiceAreas.getRowFilter(row);
        const params = new URLSearchParams({
            latitude: String(lat),
            longitude: String(lng),
        });
        if (filter.district_id) {
            params.set('district_id', filter.district_id);
        }
        if (filter.area_id) {
            params.set('area_id', filter.area_id);
        }

        const res = await fetch(resolveUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { neighborhood: null };

        if (requestId !== resolveRequestId) {
            return;
        }

        if (!data.neighborhood) {
            showNotice(notFoundLabel);
            return;
        }

        await applyNeighborhood(data.neighborhood);
    }

    map.on('click', function (event) {
        resolveFromMapClick(event.latlng.lat, event.latlng.lng);
    });

    window.DriverServiceAreas.onFilterChange(function () {
        renderMarkersForActiveRow();
        refreshMarkerSelectionStyles();
    });

    const activeRow = window.DriverServiceAreas.getActiveRow();
    if (activeRow) {
        const neighborhoodSelect = activeRow.querySelector('.driver-service-area-neighborhood');
        if (neighborhoodSelect && !neighborhoodSelect.dataset.mapBound) {
            neighborhoodSelect.dataset.mapBound = '1';
            neighborhoodSelect.addEventListener('change', refreshMarkerSelectionStyles);
        }
    }

    document.getElementById('driver_service_areas_container')?.addEventListener('change', function (event) {
        if (event.target && event.target.classList.contains('driver-service-area-neighborhood')) {
            refreshMarkerSelectionStyles();
        }
    });

    setTimeout(function () {
        map.invalidateSize();
        renderMarkersForActiveRow();
    }, 120);
})();
</script>
