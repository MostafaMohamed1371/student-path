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
    const selectSubDistrictLabel = @json(__('dashboard.select_sub_district'));
    const addressEntryLabel = @json(__('dashboard.address_entry'));
    const activeRowClass = 'driver-service-area-row--active';

    let activeRow = container.querySelector('.driver-service-area-row');
    let filterChangeHandler = null;

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

    async function loadAreas(row, districtId, keepArea, keepNeighborhood) {
        const areaSelect = row.querySelector('.driver-service-area-area');
        const neighborhoodSelect = row.querySelector('.driver-service-area-neighborhood');
        if (!areaSelect || !neighborhoodSelect) {
            return;
        }

        if (!districtId) {
            setSingleOptions(areaSelect, [], selectDistrictLabel, '');
            setSingleOptions(neighborhoodSelect, [], selectSubDistrictLabel, '');
            areaSelect.disabled = true;
            neighborhoodSelect.disabled = true;
            notifyFilterChange(row);
            return;
        }

        const res = await fetch(areasUrl + '?district_id=' + encodeURIComponent(districtId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = res.ok ? await res.json() : { areas: [] };
        setSingleOptions(areaSelect, data.areas || [], selectDistrictLabel, keepArea ? areaSelect.value : '');
        areaSelect.disabled = false;
        await loadNeighborhoods(row, districtId, areaSelect.value || '', keepNeighborhood);
        notifyFilterChange(row);
    }

    async function loadNeighborhoods(row, districtId, areaId, keepNeighborhood) {
        const neighborhoodSelect = row.querySelector('.driver-service-area-neighborhood');
        if (!neighborhoodSelect) {
            return;
        }

        if (!districtId) {
            setSingleOptions(neighborhoodSelect, [], selectSubDistrictLabel, '');
            neighborhoodSelect.disabled = true;
            notifyFilterChange(row);
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
        setSingleOptions(
            neighborhoodSelect,
            data.neighborhoods || [],
            selectSubDistrictLabel,
            keepNeighborhood ? neighborhoodSelect.value : '',
        );
        neighborhoodSelect.disabled = false;
        notifyFilterChange(row);
    }

    function getRowFilter(row) {
        const districtSelect = row.querySelector('.driver-service-area-district');
        const areaSelect = row.querySelector('.driver-service-area-area');

        return {
            district_id: districtSelect ? String(districtSelect.value || '') : '',
            area_id: areaSelect ? String(areaSelect.value || '') : '',
        };
    }

    function setActiveRow(row) {
        if (!row) {
            return;
        }

        activeRow = row;
        container.querySelectorAll('.driver-service-area-row').forEach(function (item) {
            item.classList.toggle(activeRowClass, item === row);
        });
        notifyFilterChange(row);
    }

    async function applyNeighborhoodSelection(row, payload) {
        const districtSelect = row.querySelector('.driver-service-area-district');
        const areaSelect = row.querySelector('.driver-service-area-area');
        const neighborhoodSelect = row.querySelector('.driver-service-area-neighborhood');
        if (!districtSelect || !areaSelect || !neighborhoodSelect) {
            return false;
        }

        const districtId = String(payload.district_id || '');
        const areaId = String(payload.area_id || '');
        const neighborhoodId = String(payload.neighborhood_id || '');

        if (!districtId || !areaId || !neighborhoodId) {
            return false;
        }

        districtSelect.value = districtId;
        await loadAreas(row, districtId, false, false);
        areaSelect.value = areaId;
        await loadNeighborhoods(row, districtId, areaId, false);
        neighborhoodSelect.value = neighborhoodId;

        notifyFilterChange(row);
        return true;
    }

    function notifyFilterChange(row) {
        if (typeof filterChangeHandler === 'function') {
            filterChangeHandler(row || activeRow);
        }
    }

    function bindRow(row) {
        const districtSelect = row.querySelector('.driver-service-area-district');
        const areaSelect = row.querySelector('.driver-service-area-area');
        const removeButton = row.querySelector('.driver-service-area-remove');

        row.addEventListener('mousedown', function () {
            setActiveRow(row);
        });

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
                const wasActive = row === activeRow;
                row.remove();
                renumberRows();
                if (wasActive) {
                    setActiveRow(container.querySelector('.driver-service-area-row'));
                } else {
                    notifyFilterChange(activeRow);
                }
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
        setActiveRow(row);
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
    if (activeRow) {
        setActiveRow(activeRow);
    }

    window.DriverServiceAreas = {
        getActiveRow: function () {
            return activeRow;
        },
        setActiveRow: setActiveRow,
        getRowFilter: getRowFilter,
        applyNeighborhoodSelection: applyNeighborhoodSelection,
        onFilterChange: function (handler) {
            filterChangeHandler = handler;
        },
    };
})();
</script>
<style>
    .driver-service-area-row--active {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 1px #3b82f6;
        background: #eff6ff !important;
    }
    .driver-map-marker--neighborhood {
        background: #2563eb;
        border: 2px solid #fff;
        border-radius: 50%;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
    }
    .driver-map-marker--neighborhood.is-selected {
        background: #16a34a;
    }
</style>
