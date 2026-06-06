<script>
(function () {
    const prefix = @json($iraqLocationPrefix ?? 'filter');
    const neighborhoodMultiple = @json($neighborhoodMultiple ?? false);
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

    function selectedNeighborhoodValues() {
        if (!neighborhoodMultiple) {
            return [String(neighborhoodSelect.value || '')].filter(function (value) {
                return value !== '';
            });
        }

        return Array.from(neighborhoodSelect.selectedOptions).map(function (option) {
            return String(option.value);
        }).filter(function (value) {
            return value !== '';
        });
    }

    function setSingleOptions(select, items, emptyValue, emptyLabel, selectedValue) {
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

    function setMultiNeighborhoodOptions(items, selectedValues) {
        const selectedSet = new Set((selectedValues || selectedNeighborhoodValues()).map(String));
        neighborhoodSelect.innerHTML = '';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.name;
            if (selectedSet.has(opt.value)) {
                opt.selected = true;
            }
            neighborhoodSelect.appendChild(opt);
        });
    }

    function setNeighborhoodOptions(items, emptyValue, emptyLabel, keepSelection) {
        if (neighborhoodMultiple) {
            setMultiNeighborhoodOptions(items, keepSelection ? selectedNeighborhoodValues() : []);
            return;
        }

        setSingleOptions(
            neighborhoodSelect,
            items,
            emptyValue,
            emptyLabel,
            keepSelection ? neighborhoodSelect.value : '',
        );
    }

    async function loadAreas(districtId, keepArea, keepNeighborhood) {
        if (!districtId) {
            setSingleOptions(areaSelect, [], isFilter ? '0' : '', isFilter ? allDistrictsLabel : selectDistrictLabel, '');
            setNeighborhoodOptions([], isFilter ? '0' : '', isFilter ? allSubDistrictsLabel : selectSubDistrictLabel, false);
            areaSelect.disabled = true;
            neighborhoodSelect.disabled = true;
            return;
        }

        const res = await fetch(areasUrl + '?district_id=' + encodeURIComponent(districtId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { areas: [] };
        setSingleOptions(
            areaSelect,
            data.areas || [],
            isFilter ? '0' : '',
            isFilter ? allDistrictsLabel : selectDistrictLabel,
            keepArea ? areaSelect.value : '',
        );
        areaSelect.disabled = false;
        await loadNeighborhoods(districtId, areaSelect.value || '', keepNeighborhood);
    }

    async function loadNeighborhoods(districtId, areaId, keepNeighborhood) {
        if (!districtId) {
            setNeighborhoodOptions([], isFilter ? '0' : '', isFilter ? allSubDistrictsLabel : selectSubDistrictLabel, false);
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
        setNeighborhoodOptions(
            data.neighborhoods || [],
            isFilter ? '0' : '',
            isFilter ? allSubDistrictsLabel : selectSubDistrictLabel,
            keepNeighborhood,
        );
        neighborhoodSelect.disabled = false;
    }

    districtSelect.addEventListener('change', function () {
        const districtId = districtSelect.value;
        areaSelect.value = isFilter ? '0' : '';
        if (!neighborhoodMultiple) {
            neighborhoodSelect.value = isFilter ? '0' : '';
        }
        loadAreas(districtId, false, false);
    });

    areaSelect.addEventListener('change', function () {
        if (!neighborhoodMultiple) {
            neighborhoodSelect.value = isFilter ? '0' : '';
        }
        loadNeighborhoods(districtSelect.value, areaSelect.value, false);
    });

    if (districtSelect.value) {
        areaSelect.disabled = false;
        neighborhoodSelect.disabled = false;
    }
})();
</script>
