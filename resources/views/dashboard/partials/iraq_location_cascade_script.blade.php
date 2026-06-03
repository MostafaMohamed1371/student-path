<script>
(function () {
    const prefix = @json($iraqLocationPrefix ?? 'filter');
    const areasUrl = @json(route('dashboard.locations.areas'));
    const neighborhoodsUrl = @json(route('dashboard.locations.neighborhoods'));
    const allDistrictsLabel = @json(__('dashboard.report_filter_all_districts'));
    const allSubDistrictsLabel = @json(__('dashboard.report_filter_all_sub_districts'));
    const selectDistrictLabel = @json(__('dashboard.select_district'));
    const selectSubDistrictLabel = @json(__('dashboard.select_sub_district'));
    const isFilter = prefix === 'filter';

    const districtSelect = document.getElementById('iraq_' + prefix + '_district_id');
    const areaSelect = document.getElementById('iraq_' + prefix + '_area_id');
    const neighborhoodSelect = document.getElementById('iraq_' + prefix + '_neighborhood_id');

    if (!districtSelect || !areaSelect || !neighborhoodSelect) {
        return;
    }

    function setOptions(select, items, emptyValue, emptyLabel, selectedValue) {
        const prev = String(selectedValue ?? select.value ?? '');
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = emptyValue;
        empty.textContent = emptyLabel;
        select.appendChild(empty);
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.name;
            if (prev !== '' && prev === opt.value) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    async function loadAreas(districtId, keepArea, keepNeighborhood) {
        if (!districtId) {
            setOptions(areaSelect, [], isFilter ? '0' : '', isFilter ? allDistrictsLabel : selectDistrictLabel, '');
            setOptions(neighborhoodSelect, [], isFilter ? '0' : '', isFilter ? allSubDistrictsLabel : selectSubDistrictLabel, '');
            areaSelect.disabled = true;
            neighborhoodSelect.disabled = true;
            return;
        }

        const res = await fetch(areasUrl + '?district_id=' + encodeURIComponent(districtId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { areas: [] };
        setOptions(areaSelect, data.areas || [], isFilter ? '0' : '', isFilter ? allDistrictsLabel : selectDistrictLabel, keepArea ? areaSelect.value : '');
        areaSelect.disabled = false;
        await loadNeighborhoods(districtId, areaSelect.value || '', keepNeighborhood);
    }

    async function loadNeighborhoods(districtId, areaId, keepNeighborhood) {
        if (!districtId) {
            setOptions(neighborhoodSelect, [], isFilter ? '0' : '', isFilter ? allSubDistrictsLabel : selectSubDistrictLabel, '');
            neighborhoodSelect.disabled = true;
            return;
        }

        const params = new URLSearchParams({ district_id: districtId });
        if (areaId) {
            params.set('area_id', areaId);
        }
        const res = await fetch(neighborhoodsUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { neighborhoods: [] };
        setOptions(neighborhoodSelect, data.neighborhoods || [], isFilter ? '0' : '', isFilter ? allSubDistrictsLabel : selectSubDistrictLabel, keepNeighborhood ? neighborhoodSelect.value : '');
        neighborhoodSelect.disabled = false;
    }

    districtSelect.addEventListener('change', function () {
        const districtId = districtSelect.value;
        areaSelect.value = isFilter ? '0' : '';
        neighborhoodSelect.value = isFilter ? '0' : '';
        loadAreas(districtId, false, false);
    });

    areaSelect.addEventListener('change', function () {
        neighborhoodSelect.value = isFilter ? '0' : '';
        loadNeighborhoods(districtSelect.value, areaSelect.value, false);
    });

    if (districtSelect.value) {
        areaSelect.disabled = false;
        neighborhoodSelect.disabled = false;
    }
})();
</script>
