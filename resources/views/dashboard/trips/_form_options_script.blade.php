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
    if (!url || !schoolSelect || !tripTypeSelect || !driverSelect || !studentsSelect) {
        return;
    }

    const placeholderSchool = @json($tripFormPlaceholderSchool);
    const placeholderShift = @json($tripFormPlaceholderShift);

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

    async function refreshTripFormOptions() {
        const schoolId = schoolSelect.value;
        const tripType = tripTypeSelect.value;
        const includeIds = selectedStudentIds();

        if (!schoolId) {
            setSelectOptions(driverSelect, [], '—', false);
            studentsSelect.innerHTML = '';
            const hint = document.createElement('option');
            hint.disabled = true;
            hint.textContent = placeholderSchool;
            studentsSelect.appendChild(hint);
            return;
        }

        if (!tripType) {
            setSelectOptions(driverSelect, [], '—', true);
            studentsSelect.innerHTML = '';
            const hint = document.createElement('option');
            hint.disabled = true;
            hint.textContent = placeholderShift;
            studentsSelect.appendChild(hint);
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
            setSelectOptions(driverSelect, data.drivers || [], '—', true);
            setMultiSelectOptions(studentsSelect, data.students || [], includeIds);
        } catch (e) {
            console.error(e);
        }
    }

    schoolSelect.addEventListener('change', function () {
        driverSelect.value = '';
        refreshTripFormOptions();
    });
    tripTypeSelect.addEventListener('change', refreshTripFormOptions);
    refreshTripFormOptions();
})();
</script>
