<script>
(function () {
    const container = document.getElementById('driver_service_areas_container');
    const addButton = document.getElementById('driver_service_area_add');
    const template = document.getElementById('driver_service_area_template');

    if (!container || !addButton || !template) {
        return;
    }

    const areasUrl = @json(route('dashboard.locations.areas'));
    const neighborhoodsUrl = @json(route('dashboard.locations.neighborhoods'));
    const selectDistrictLabel = @json(__('dashboard.select_district'));
    const addressEntryLabel = @json(__('dashboard.address_entry'));

    function nextIndex() {
        const rows = container.querySelectorAll('.driver-service-area-row');
        let max = -1;
        rows.forEach(function (row) {
            const idx = parseInt(row.getAttribute('data-index') || '0', 10);
            if (!Number.isNaN(idx) && idx > max) {
                max = idx;
            }
        });

        return max + 1;
    }

    function renumberRows() {
        const rows = container.querySelectorAll('.driver-service-area-row');
        rows.forEach(function (row, position) {
            row.setAttribute('data-index', String(position));
            row.querySelectorAll('[name^="service_areas["]').forEach(function (input) {
                input.name = input.name.replace(/service_areas\[\d+\]/, 'service_areas[' + position + ']');
            });
            row.querySelectorAll('[data-index]').forEach(function (el) {
                el.setAttribute('data-index', String(position));
            });
            const title = row.querySelector('.driver-service-area-title');
            if (title) {
                title.textContent = addressEntryLabel + ' #' + (position + 1);
            }
            const removeBtn = row.querySelector('.driver-service-area-remove');
            if (removeBtn) {
                removeBtn.hidden = rows.length <= 1;
            }
        });
    }

    function selectedNeighborhoodValues(select) {
        return Array.from(select.selectedOptions).map(function (option) {
            return String(option.value);
        }).filter(function (value) {
            return value !== '';
        });
    }

    function setSingleOptions(select, items, emptyLabel, selectedValue) {
        const prev = String(selectedValue ?? select.value ?? '');
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
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

    function setMultiNeighborhoodOptions(select, items, selectedValues) {
        const selectedSet = new Set((selectedValues || selectedNeighborhoodValues(select)).map(String));
        select.innerHTML = '';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.name;
            if (selectedSet.has(opt.value)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    async function loadAreas(row, districtId, keepArea, keepNeighborhood) {
        const areaSelect = row.querySelector('.driver-service-area-area');
        const neighborhoodSelect = row.querySelector('.driver-service-area-neighborhood');
        if (!areaSelect || !neighborhoodSelect) {
            return;
        }

        if (!districtId) {
            setSingleOptions(areaSelect, [], selectDistrictLabel, '');
            setMultiNeighborhoodOptions(neighborhoodSelect, [], []);
            areaSelect.disabled = true;
            neighborhoodSelect.disabled = true;
            return;
        }

        const res = await fetch(areasUrl + '?district_id=' + encodeURIComponent(districtId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { areas: [] };
        setSingleOptions(areaSelect, data.areas || [], selectDistrictLabel, keepArea ? areaSelect.value : '');
        areaSelect.disabled = false;
        await loadNeighborhoods(row, districtId, areaSelect.value || '', keepNeighborhood);
    }

    async function loadNeighborhoods(row, districtId, areaId, keepNeighborhood) {
        const neighborhoodSelect = row.querySelector('.driver-service-area-neighborhood');
        if (!neighborhoodSelect) {
            return;
        }

        if (!districtId) {
            setMultiNeighborhoodOptions(neighborhoodSelect, [], []);
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
        setMultiNeighborhoodOptions(
            neighborhoodSelect,
            data.neighborhoods || [],
            keepNeighborhood ? selectedNeighborhoodValues(neighborhoodSelect) : [],
        );
        neighborhoodSelect.disabled = false;
    }

    function bindRow(row) {
        const districtSelect = row.querySelector('.driver-service-area-district');
        const areaSelect = row.querySelector('.driver-service-area-area');
        const removeButton = row.querySelector('.driver-service-area-remove');

        if (districtSelect && !districtSelect.dataset.bound) {
            districtSelect.dataset.bound = '1';
            districtSelect.addEventListener('change', function () {
                if (areaSelect) {
                    areaSelect.value = '';
                }
                loadAreas(row, districtSelect.value, false, false);
            });
        }

        if (areaSelect && !areaSelect.dataset.bound) {
            areaSelect.dataset.bound = '1';
            areaSelect.addEventListener('change', function () {
                loadNeighborhoods(row, districtSelect ? districtSelect.value : '', areaSelect.value, false);
            });
        }

        if (removeButton && !removeButton.dataset.bound) {
            removeButton.dataset.bound = '1';
            removeButton.addEventListener('click', function () {
                const rows = container.querySelectorAll('.driver-service-area-row');
                if (rows.length <= 1) {
                    return;
                }
                row.remove();
                renumberRows();
            });
        }
    }

    function addRow() {
        const index = nextIndex();
        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        if (!row) {
            return;
        }
        container.appendChild(row);
        bindRow(row);
        renumberRows();
    }

    addButton.addEventListener('click', addRow);

    container.querySelectorAll('.driver-service-area-row').forEach(function (row) {
        bindRow(row);
        const districtSelect = row.querySelector('.driver-service-area-district');
        if (districtSelect && districtSelect.value) {
            const areaSelect = row.querySelector('.driver-service-area-area');
            const neighborhoodSelect = row.querySelector('.driver-service-area-neighborhood');
            if (areaSelect) {
                areaSelect.disabled = false;
            }
            if (neighborhoodSelect) {
                neighborhoodSelect.disabled = false;
            }
        }
    });

    renumberRows();
})();
</script>
