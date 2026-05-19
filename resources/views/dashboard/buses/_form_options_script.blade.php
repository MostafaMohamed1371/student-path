<script>
(function () {
    const url = @json($formOptionsUrl ?? '');
    const exceptBusId = @json(isset($bus) && $bus ? (int) $bus->id : null);
    const schoolSelect = document.getElementById('bus_form_school_id');
    const driverSelect = document.getElementById('driver_id');
    const placeholderDriver = @json(__('dashboard.bus_select_driver_after_school'));

    if (!url || !schoolSelect || !driverSelect) {
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
        if (!schoolId) {
            setDriverOptions([], false);
            return;
        }
        try {
            const params = new URLSearchParams({ school_id: schoolId });
            if (exceptBusId) {
                params.set('except_bus_id', String(exceptBusId));
            }
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
    });
    refreshDrivers();
})();
</script>
