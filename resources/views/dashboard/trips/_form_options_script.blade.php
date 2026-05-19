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

    if (!url || !schoolSelect || !tripTypeSelect || !driverSelect) {
        return;
    }

    const placeholderSchool = @json($tripFormPlaceholderSchool);
    const placeholderShift = @json($tripFormPlaceholderShift);
    const routeFromDriverHint = @json(__('dashboard.trip_route_from_driver_route'));
    const noRouteForDriverHint = @json(__('dashboard.trip_no_route_for_driver'));
    const distanceNeedsSchoolCoordsHint = @json(__('dashboard.trip_distance_needs_school_coords'));
    const studentsRouteFilterHint = @json(__('dashboard.trip_students_route_filter_help'));

    let driversCache = [];

    function haversineKm(lat1, lon1, lat2, lon2) {
        const earthKm = 6371.0;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLon / 2) * Math.sin(dLon / 2);

        return Math.round(earthKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)) * 100) / 100;
    }

    function resolveRoutePath(row) {
        if (row.location != null && String(row.location).trim() !== '') {
            return String(row.location);
        }
        const start = row.start_address != null ? String(row.start_address).trim() : '';
        const end = row.end_address != null ? String(row.end_address).trim() : '';
        if (start !== '' && end !== '') {
            return start + ' → ' + end;
        }

        return start !== '' ? start : end;
    }

    function resolveDistanceKm(row) {
        if (row.distance_km != null && !isNaN(parseFloat(row.distance_km))) {
            return Number(parseFloat(row.distance_km).toFixed(2));
        }
        const slat = row.route_start_latitude;
        const slng = row.route_start_longitude;
        const elat = row.school_latitude;
        const elng = row.school_longitude;
        if (slat == null || slng == null || elat == null || elng == null) {
            return null;
        }

        return haversineKm(parseFloat(slat), parseFloat(slng), parseFloat(elat), parseFloat(elng));
    }

    function selectedStudentIds() {
        if (!studentsSelect) {
            return [];
        }

        return Array.from(studentsSelect.selectedOptions)
            .map(function (o) { return parseInt(o.value, 10); })
            .filter(function (n) { return !isNaN(n); });
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

    function clearAutoFields() {
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
    }

    function applyDriverBusFields() {
        const driverId = String(driverSelect.value || '');
        if (!driverId) {
            clearAutoFields();
            return;
        }

        const row = driversCache.find(function (d) { return String(d.id) === driverId; });
        if (!row) {
            return;
        }

        if (busNumberInput) {
            busNumberInput.value = row.bus_number != null && row.bus_number !== ''
                ? String(row.bus_number)
                : '';
        }

        if (row.transport_route_id) {
            if (routeTitleInput) {
                routeTitleInput.value = row.route_title != null ? String(row.route_title) : '';
            }
            const routePath = resolveRoutePath(row);
            if (locationInput) {
                locationInput.value = routePath;
            }
            const distanceKm = resolveDistanceKm(row);
            if (distanceKmInput) {
                distanceKmInput.value = distanceKm != null ? String(distanceKm) : '0';
            }
            if (routeHint) {
                routeHint.style.display = 'block';
                var distText = distanceKm != null ? ' (' + distanceKm + ' km)' : '';
                var extraHint = distanceKm == null && routePath !== '' ? ' ' + distanceNeedsSchoolCoordsHint : '';
                routeHint.textContent = routeFromDriverHint + distText + extraHint;
            }

            var routeStudentCount = Array.isArray(row.route_student_ids) ? row.route_student_ids.length : 0;
            if (studentsCountInput) {
                studentsCountInput.value = String(routeStudentCount);
            }
            if (studentsSelect && routeStudentCount > 0) {
                const routeIds = row.route_student_ids.map(String);
                Array.from(studentsSelect.options).forEach(function (opt) {
                    opt.selected = routeIds.indexOf(opt.value) !== -1;
                });
            }
        } else {
            if (routeTitleInput) {
                routeTitleInput.value = '';
            }
            if (locationInput) {
                locationInput.value = '';
            }
            if (distanceKmInput) {
                distanceKmInput.value = '0';
            }
            if (studentsCountInput && row.students_count != null) {
                studentsCountInput.value = String(row.students_count);
            }
            if (routeHint) {
                routeHint.style.display = 'block';
                routeHint.textContent = noRouteForDriverHint;
            }
        }
    }

    function setStudentsPlaceholder(message) {
        if (!studentsSelect) {
            return;
        }
        studentsSelect.innerHTML = '';
        const hint = document.createElement('option');
        hint.disabled = true;
        hint.textContent = message;
        studentsSelect.appendChild(hint);
    }

    async function refreshTripFormOptions() {
        const schoolId = schoolSelect.value;
        const tripType = tripTypeSelect.value;
        const includeIds = selectedStudentIds();

        if (!schoolId) {
            driversCache = [];
            setSelectOptions(driverSelect, [], '—', false);
            setStudentsPlaceholder(placeholderSchool);
            if (studentsFilterHint) {
                studentsFilterHint.style.display = 'none';
            }
            clearAutoFields();
            return;
        }

        if (!tripType) {
            driversCache = [];
            setSelectOptions(driverSelect, [], '—', false);
            setStudentsPlaceholder(placeholderShift);
            if (studentsFilterHint) {
                studentsFilterHint.style.display = 'none';
            }
            clearAutoFields();
            return;
        }

        const params = new URLSearchParams({ school_id: schoolId, trip_type: tripType });
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
            const keepDriver = String(driverSelect.value || '');
            setSelectOptions(driverSelect, driversCache, '—', true);
            if (keepDriver && !driversCache.some(function (d) { return String(d.id) === keepDriver; })) {
                driverSelect.value = '';
            }
            if (studentsSelect) {
                setMultiSelectOptions(studentsSelect, data.students || [], includeIds);
            }
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
    tripTypeSelect.addEventListener('change', function () {
        driverSelect.value = '';
        refreshTripFormOptions();
    });
    driverSelect.addEventListener('change', async function () {
        const driverId = String(driverSelect.value || '');
        if (driverId && !driversCache.some(function (d) { return String(d.id) === driverId; })) {
            await refreshTripFormOptions();
            return;
        }
        applyDriverBusFields();
    });

    refreshTripFormOptions();
})();
</script>
