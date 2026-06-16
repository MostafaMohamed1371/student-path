<script>
(function () {
    const formOptionsUrl = @json($formOptionsUrl ?? '');
    const schoolField = document.getElementById('school_id');
    const busSelect = document.getElementById('bus_id');
    const exceptDriverId = @json(isset($driver) && $driver ? (int) $driver->id : 0);
    const placeholder = @json(__('dashboard.driver_select_bus'));
    const selectSchoolFirst = @json(__('dashboard.driver_select_bus_after_school'));

    if (!formOptionsUrl || !schoolField || !busSelect) {
        return;
    }

    function currentSchoolId() {
        return String(schoolField.value || '').trim();
    }

    async function loadBuses(keepSelection) {
        const schoolId = currentSchoolId();
        const previous = keepSelection ? String(busSelect.value || '') : '';

        busSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = schoolId ? placeholder : selectSchoolFirst;
        busSelect.appendChild(empty);
        busSelect.disabled = !schoolId;

        if (!schoolId) {
            return;
        }

        const params = new URLSearchParams({ school_id: schoolId });
        if (exceptDriverId > 0) {
            params.set('except_driver_id', String(exceptDriverId));
        }

        try {
            const response = await fetch(formOptionsUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = response.ok ? await response.json() : { buses: [] };

            (data.buses || []).forEach(function (row) {
                const option = document.createElement('option');
                option.value = String(row.id);
                option.textContent = row.label;
                if (previous !== '' && previous === option.value) {
                    option.selected = true;
                }
                busSelect.appendChild(option);
            });
        } catch (e) {
            console.error(e);
        }
    }

    schoolField.addEventListener('change', function () {
        loadBuses(false);
    });

    if (currentSchoolId()) {
        loadBuses(true);
    }
})();
</script>
