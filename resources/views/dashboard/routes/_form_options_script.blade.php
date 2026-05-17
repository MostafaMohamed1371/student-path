<script>
(function () {
    const url = @json($formOptionsUrl ?? '');
    const schoolSelect = document.getElementById('route_form_school_id');
    const tripTypeSelect = document.getElementById('route_form_trip_type');
    const driverSelect = document.getElementById('route_form_driver_id');
    const placeholderTrip = @json(__('dashboard.trip_form_select_trip_type_for_shift'));
    const placeholderDriver = @json(__('dashboard.route_select_driver_after_filters'));

    if (!url || !schoolSelect || !tripTypeSelect || !driverSelect) {
        return;
    }

    function setDriverOptions(items, keepValue) {
        const prev = keepValue ? String(driverSelect.value || '') : '';
        driverSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholderDriver;
        driverSelect.appendChild(empty);
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (prev && prev === opt.value) {
                opt.selected = true;
            }
            driverSelect.appendChild(opt);
        });
        driverSelect.disabled = items.length === 0;
    }

    async function refreshDrivers() {
        const schoolId = schoolSelect.value;
        const tripType = tripTypeSelect.value;
        if (!schoolId || !tripType) {
            setDriverOptions([], false);
            return;
        }
        try {
            const params = new URLSearchParams({ school_id: schoolId, trip_type: tripType });
            const res = await fetch(url + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            setDriverOptions(data.drivers || [], true);
        } catch (e) {
            console.error(e);
        }
    }

    schoolSelect.addEventListener('change', function () {
        driverSelect.value = '';
        refreshDrivers();
        if (typeof window.syncRouteSchoolEndpoint === 'function') {
            window.syncRouteSchoolEndpoint();
        }
    });
    tripTypeSelect.addEventListener('change', function () {
        driverSelect.value = '';
        refreshDrivers();
    });
    refreshDrivers();
})();
</script>
