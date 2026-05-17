@php
    $tripFormPlaceholderSchool = __('dashboard.trip_form_select_school_first');
    $tripFormPlaceholderShift = __('dashboard.trip_form_select_trip_type_for_shift');
@endphp
<script>
(function () {
    const url = @json($formOptionsUrl ?? '');
    const schoolSelect = document.getElementById('trip_form_school_id');
    const tripTypeSelect = document.getElementById('trip_form_trip_type');
    const driverSelect = document.getElementById('trip_form_driver_id');
    const studentsSelect = document.getElementById('trip_form_student_ids');
    const busNumberInput = document.getElementById('trip_form_bus_number');
    const studentsCountInput = document.getElementById('trip_form_students_count');
    if (!url || !schoolSelect || !tripTypeSelect || !driverSelect || !studentsSelect) {
        return;
    }

    const placeholderSchool = @json($tripFormPlaceholderSchool);
    const placeholderShift = @json($tripFormPlaceholderShift);

    let driversCache = [];

    function selectedStudentIds() {
        return Array.from(studentsSelect.selectedOptions).map(function (o) { return parseInt(o.value, 10); }).filter(function (n) { return !isNaN(n); });
    }

    function setSelectOptions(select, items, placeholder, keepValue) {
        const prev = keepValue ? String(select.value || '') : '';
        select.innerHTML = '';
        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            select.appendChild(opt);
        }
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (prev && prev === opt.value) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    function setMultiSelectOptions(select, items, selectedIds) {
        const selected = new Set((selectedIds || []).map(String));
        select.innerHTML = '';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (selected.has(opt.value)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    function applyDriverBusFields() {
        const driverId = String(driverSelect.value || '');
        if (!driverId) {
            if (busNumberInput) {
                busNumberInput.value = '';
            }
            if (studentsCountInput) {
                studentsCountInput.value = '0';
            }
            return;
        }

        const row = driversCache.find(function (d) { return String(d.id) === driverId; });
        if (!row) {
            return;
        }

        if (busNumberInput && row.bus_number != null && row.bus_number !== '') {
            busNumberInput.value = row.bus_number;
        }
        if (studentsCountInput && row.students_count != null) {
            studentsCountInput.value = String(row.students_count);
        }
    }

    async function refreshTripFormOptions() {
        const schoolId = schoolSelect.value;
        const tripType = tripTypeSelect.value;
        const includeIds = selectedStudentIds();

        if (!schoolId) {
            driversCache = [];
            setSelectOptions(driverSelect, [], '—', false);
            studentsSelect.innerHTML = '';
            const hint = document.createElement('option');
            hint.disabled = true;
            hint.textContent = placeholderSchool;
            studentsSelect.appendChild(hint);
            applyDriverBusFields();
            return;
        }

        if (!tripType) {
            driversCache = [];
            setSelectOptions(driverSelect, [], '—', true);
            studentsSelect.innerHTML = '';
            const hint = document.createElement('option');
            hint.disabled = true;
            hint.textContent = placeholderShift;
            studentsSelect.appendChild(hint);
            applyDriverBusFields();
            return;
        }

        const params = new URLSearchParams({ school_id: schoolId, trip_type: tripType });
        includeIds.forEach(function (id) { params.append('include_student_ids[]', String(id)); });

        try {
            const res = await fetch(url + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            driversCache = data.drivers || [];
            setSelectOptions(driverSelect, driversCache, '—', true);
            setMultiSelectOptions(studentsSelect, data.students || [], includeIds);
            applyDriverBusFields();
        } catch (e) {
            console.error(e);
        }
    }

    schoolSelect.addEventListener('change', function () {
        driverSelect.value = '';
        refreshTripFormOptions();
    });
    tripTypeSelect.addEventListener('change', refreshTripFormOptions);
    driverSelect.addEventListener('change', applyDriverBusFields);
    refreshTripFormOptions();
})();
</script>
