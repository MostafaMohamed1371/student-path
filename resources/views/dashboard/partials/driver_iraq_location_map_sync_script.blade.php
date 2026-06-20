<script>
(function () {
    const registryKey = 'driver_service_area';
    const mapElementId = 'driver_service_area_map';
    const neighborhoodsUrl = @json(route('dashboard.locations.neighborhoods'));
    const notFoundLabel = @json(__('dashboard.driver_service_area_map_not_found'));
    const selectRowLabel = @json(__('dashboard.driver_service_area_map_select_row'));

    const mapEl = document.getElementById(mapElementId);
    let noticeEl = document.getElementById('driver_service_area_map_notice');
    if (!noticeEl && mapEl && mapEl.parentNode) {
        noticeEl = document.createElement('p');
        noticeEl.id = 'driver_service_area_map_notice';
        noticeEl.style.cssText = 'display:none;margin:8px 0 0;font-size:12px;color:#b45309;';
        mapEl.parentNode.insertBefore(noticeEl, mapEl.nextSibling);
    }

    let neighborhoodCache = [];
    let markerRequestId = 0;

    function getRegistry() {
        return (window.IraqLocationMapRegistry || {})[registryKey] || null;
    }

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

    function activeRow() {
        return window.DriverServiceAreas ? window.DriverServiceAreas.getActiveRow() : null;
    }

    function rowSelects(row) {
        if (!row) {
            return null;
        }
        return {
            district: row.querySelector('.driver-service-area-district'),
            area: row.querySelector('.driver-service-area-area'),
            neighborhood: row.querySelector('.driver-service-area-neighborhood'),
        };
    }

    function selectedNeighborhoodId(select) {
        if (!select) {
            return '';
        }

        return String(select.value || '');
    }

    async function refreshNeighborhoodMarkers(options) {
        const registry = getRegistry();
        const row = activeRow();
        const selects = rowSelects(row);
        if (!registry || !selects || !selects.district) {
            return;
        }

        options = options || {};
        const districtId = selects.district.value;
        const areaId = selects.area ? selects.area.value : '';
        const requestId = ++markerRequestId;

        if (!districtId) {
            registry.clearNeighborhoodMarkers?.();
            neighborhoodCache = [];
            return;
        }

        const params = new URLSearchParams({
            district_id: districtId,
            with_coordinates: '1',
        });
        if (areaId) {
            params.set('area_id', areaId);
        }

        const res = await fetch(neighborhoodsUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { neighborhoods: [] };

        if (requestId !== markerRequestId) {
            return;
        }

        neighborhoodCache = (data.neighborhoods || []).filter(function (item) {
            return item.latitude != null && item.longitude != null;
        });

        registry.showNeighborhoodMarkers?.(
            neighborhoodCache,
            selectedNeighborhoodId(selects.neighborhood),
            options || {},
        );
    }

    function onNeighborhoodDropdownChange() {
        const row = activeRow();
        const selects = rowSelects(row);
        const registry = getRegistry();
        if (!selects || !registry) {
            return;
        }

        const selectedId = selectedNeighborhoodId(selects.neighborhood);
        const item = neighborhoodCache.find(function (row) {
            return String(row.id) === selectedId;
        });

        if (item) {
            registry.focusNeighborhood?.(item, { updatePickupMarker: true, panMap: true });
            showNotice('');
        }

        registry.showNeighborhoodMarkers?.(neighborhoodCache, selectedId);
    }

    function bindRowListeners() {
        const container = document.getElementById('driver_service_areas_container');
        if (!container || container.dataset.mapSyncBound) {
            return;
        }
        container.dataset.mapSyncBound = '1';

        container.addEventListener('change', function (event) {
            const target = event.target;
            if (!target || !target.classList) {
                return;
            }
            if (target.classList.contains('driver-service-area-district')
                || target.classList.contains('driver-service-area-area')) {
                showNotice('');
                refreshNeighborhoodMarkers({ fitMap: true });
                return;
            }
            if (target.classList.contains('driver-service-area-neighborhood')) {
                onNeighborhoodDropdownChange();
            }
        });
    }

    function waitForDriverMap() {
        if (!window.DriverServiceAreas) {
            setTimeout(waitForDriverMap, 50);
            return;
        }

        bindRowListeners();

        window.DriverServiceAreas.onFilterChange(function () {
            showNotice('');
            refreshNeighborhoodMarkers({ fitMap: true });
        });

        document.addEventListener('iraq-location-map-not-found', function (event) {
            const detail = event.detail || {};
            if (detail.prefix !== registryKey) {
                return;
            }
            if (detail.reason === 'no_row') {
                showNotice(selectRowLabel);
                return;
            }
            showNotice(notFoundLabel);
        });

        document.addEventListener('google-maps-auth-failure', function () {
            showNotice(@json(__('dashboard.google_maps_auth_failed')));
        });

        document.addEventListener('driver-service-area-updated', function () {
            refreshNeighborhoodMarkers({});
        });

        const row = activeRow();
        const selects = rowSelects(row);
        if (selects && selects.district && selects.district.value) {
            refreshNeighborhoodMarkers({ fitMap: true });
        }
    }

    waitForDriverMap();
})();
</script>
<style>
    .iraq-map-marker--neighborhood {
        background: #2563eb;
        border: 2px solid #fff;
        border-radius: 50%;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
    }
    .iraq-map-marker--neighborhood.is-selected {
        background: #16a34a;
    }
</style>
