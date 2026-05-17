@php
    $tripFormPlaceholderSchool = __('dashboard.trip_form_select_school_first');
    $tripFormPlaceholderShift = __('dashboard.trip_form_select_trip_type_for_shift');
@endphp
<script>
(function () {
    const url = @json($formOptionsUrl ?? '');
    const exceptTripId = @json(isset($exceptTripId) && $exceptTripId ? (int) $exceptTripId : null);
    const schoolSelect = document.getElementById('trip_form_school_id');
    const tripTypeSelect = document.getElementById('trip_form_trip_type');
    const driverSelect = document.getElementById('trip_form_driver_id');
    const studentsSelect = document.getElementById('trip_form_student_ids');
    const busNumberInput = document.getElementById('trip_form_bus_number');
    const studentsCountInput = document.getElementById('trip_form_students_count');
    const routeTitleInput = document.getElementById('trip_form_route_title');
    const locationInput = document.getElementById('trip_form_location');
    const distanceKmInput = document.getElementById('trip_form_distance_km');
    const routeHint = document.getElementById('trip_form_route_hint');
    const studentsFilterHint = document.getElementById('trip_form_students_filter_hint');
    if (!url || !schoolSelect || !tripTypeSelect || !driverSelect || !studentsSelect) {
        return;
    }

    const placeholderSchool = @json($tripFormPlaceholderSchool);
    const placeholderShift = @json($tripFormPlaceholderShift);
    const routeFromDriverHint = @json(__('dashboard.trip_route_from_driver_route'));
    const noRouteForDriverHint = @json(__('dashboard.trip_no_route_for_driver'));
    const studentsRouteFilterHint = @json(__('dashboard.trip_students_route_filter_help'));

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
            if (routeTitleInput) {
                routeTitleInput.value = '';
            }
            if (locationInput) {
                locationInput.value = '';
            }
            if (distanceKmInput) {
                distanceKmInput.value = '0';
            }
            if (routeHint) {
                routeHint.style.display = 'none';
                routeHint.textContent = '';
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

        if (row.transport_route_id) {
            if (routeTitleInput && row.route_title) {
                routeTitleInput.value = row.route_title;
            }
            if (locationInput && row.location) {
                locationInput.value = row.location;
            }
            if (distanceKmInput && row.distance_km != null && !isNaN(parseFloat(row.distance_km))) {
                distanceKmInput.value = String(Number(parseFloat(row.distance_km).toFixed(2)));
            }
            if (routeHint) {
                routeHint.style.display = 'block';
                var distText = (row.distance_km != null && !isNaN(parseFloat(row.distance_km)))
                    ? ' (' + Number(parseFloat(row.distance_km).toFixed(2)) + ' km)'
                    : '';
                routeHint.textContent = routeFromDriverHint + distText;
            }
            if (Array.isArray(row.route_student_ids) && row.route_student_ids.length > 0) {
                const routeIds = row.route_student_ids.map(String);
                Array.from(studentsSelect.options).forEach(function (opt) {
                    opt.selected = routeIds.indexOf(opt.value) !== -1;
                });
                if (studentsCountInput) {
                    studentsCountInput.value = String(routeIds.length);
                }
            }
        } else if (routeHint) {
            routeHint.style.display = 'block';
            routeHint.textContent = noRouteForDriverHint;
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
            if (studentsFilterHint) {
                studentsFilterHint.style.display = 'none';
            }
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
            if (studentsFilterHint) {
                studentsFilterHint.style.display = 'none';
            }
            applyDriverBusFields();
            return;
        }

        const params = new URLSearchParams({ school_id: schoolId, trip_type: tripType });
        const driverId = String(driverSelect.value || '');
        if (driverId) {
            params.set('driver_id', driverId);
        }
        if (exceptTripId) {
            params.set('except_trip_id', String(exceptTripId));
        }
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
            if (studentsFilterHint) {
                if (data.route_filter_active && data.corridor_max_km != null) {
                    studentsFilterHint.style.display = 'block';
                    studentsFilterHint.textContent = studentsRouteFilterHint.replace(':km', String(data.corridor_max_km));
                } else {
                    studentsFilterHint.style.display = 'none';
                    studentsFilterHint.textContent = '';
                }
            }
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
    driverSelect.addEventListener('change', refreshTripFormOptions);
    refreshTripFormOptions();
})();
</script>
