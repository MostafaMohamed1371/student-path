@php
    $iraqLocationPrefix = $iraqLocationPrefix ?? 'student';
    $mapRegistryKey = $mapRegistryKey ?? $iraqLocationPrefix;
    $mapElementId = $mapElementId ?? 'student-location-map';
@endphp
<script>
(function () {
    const prefix = @json($iraqLocationPrefix);
    const registryKey = @json($mapRegistryKey);
    const mapElementId = @json($mapElementId);
    const neighborhoodsUrl = @json(route('dashboard.locations.neighborhoods'));
    const notFoundLabel = @json(__('dashboard.driver_service_area_map_not_found'));

    const districtSelect = document.getElementById('iraq_' + prefix + '_district_id');
    const areaSelect = document.getElementById('iraq_' + prefix + '_area_id');
    const neighborhoodSelect = document.getElementById('iraq_' + prefix + '_neighborhood_id');

    if (!districtSelect || !areaSelect || !neighborhoodSelect) {
        return;
    }

    const mapEl = document.getElementById(mapElementId);
    let noticeEl = document.getElementById('iraq_' + prefix + '_map_notice');
    if (!noticeEl && mapEl && mapEl.parentNode) {
        noticeEl = document.createElement('p');
        noticeEl.id = 'iraq_' + prefix + '_map_notice';
        noticeEl.style.cssText = 'display:none;margin:0 0 12px;font-size:12px;color:#b45309;';
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

    async function refreshNeighborhoodMarkers(options) {
        const registry = getRegistry();
        if (!registry) {
            return;
        }

        const districtId = districtSelect.value;
        const areaId = areaSelect.value;
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
            neighborhoodSelect.value || '',
            options || {},
        );
    }

    function onNeighborhoodDropdownChange() {
        const selectedId = String(neighborhoodSelect.value || '');
        const registry = getRegistry();
        if (!registry) {
            return;
        }

        const item = neighborhoodCache.find(function (row) {
            return String(row.id) === selectedId;
        });

        if (item) {
            registry.focusNeighborhood?.(item, { updatePickupMarker: true, panMap: true });
            showNotice('');
        }

        registry.showNeighborhoodMarkers?.(neighborhoodCache, selectedId);
    }

    districtSelect.addEventListener('change', function () {
        showNotice('');
        refreshNeighborhoodMarkers({ fitMap: true });
    });

    areaSelect.addEventListener('change', function () {
        showNotice('');
        refreshNeighborhoodMarkers({ fitMap: true });
    });

    neighborhoodSelect.addEventListener('change', onNeighborhoodDropdownChange);

    document.addEventListener('iraq-location-set', function (event) {
        const detail = event.detail || {};
        if (detail.prefix !== prefix) {
            return;
        }
        refreshNeighborhoodMarkers();
    });

    document.addEventListener('iraq-location-cascade-updated', function (event) {
        const detail = event.detail || {};
        if (detail.prefix !== prefix) {
            return;
        }
        refreshNeighborhoodMarkers({ fitMap: detail.fitMap === true });
    });

    document.addEventListener('iraq-location-map-not-found', function (event) {
        const detail = event.detail || {};
        if (detail.prefix !== prefix) {
            return;
        }
        showNotice(notFoundLabel);
    });

    if (districtSelect.value) {
        refreshNeighborhoodMarkers({ fitMap: true });
    }
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
