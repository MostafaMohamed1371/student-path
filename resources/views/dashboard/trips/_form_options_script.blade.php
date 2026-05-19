@php
    $tripFormPlaceholderSchool = __('dashboard.trip_form_select_school_first');
    $tripFormPlaceholderShift = __('dashboard.trip_form_select_trip_type_for_shift');
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const driversUrl = @json($formOptionsUrl ?? '');
    const autoFillUrl = @json($driverAutoFillUrl ?? '');
    const exceptTripId = @json(isset($exceptTripId) && $exceptTripId ? (int) $exceptTripId : null);

    const schoolSelect = document.getElementById('trip_form_school_id');
    const tripTypeSelect = document.getElementById('trip_form_trip_type');
    const driverSelect = document.getElementById('trip_form_driver_id');
    const busNumberInput = document.getElementById('trip_form_bus_number');
    const studentsCountInput = document.getElementById('trip_form_students_count');
    const routeTitleInput = document.getElementById('trip_form_route_title');
    const locationInput = document.getElementById('trip_form_location');
    const distanceKmInput = document.getElementById('trip_form_distance_km');
    const routeHint = document.getElementById('trip_form_route_hint');

    if (!driversUrl || !autoFillUrl || !schoolSelect || !tripTypeSelect || !driverSelect) {
        return;
    }

    const placeholderShift = @json($tripFormPlaceholderShift);
    const routeFromDriverHint = @json(__('dashboard.trip_route_from_driver_route'));
    const noRouteForDriverHint = @json(__('dashboard.trip_no_route_for_driver'));
    const distanceNeedsSchoolCoordsHint = @json(__('dashboard.trip_distance_needs_school_coords'));
    const loadFailedHint = @json(__('dashboard.trip_auto_fill_failed'));

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

    function applyAutoFillPayload(row) {
        if (busNumberInput) {
            busNumberInput.value = row.bus_number != null && row.bus_number !== ''
                ? String(row.bus_number)
                : '';
        }

        if (!row.has_route && !row.transport_route_id) {
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

            return;
        }

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

        if (studentsCountInput && row.students_count != null) {
            studentsCountInput.value = String(row.students_count);
        }

        if (routeHint) {
            routeHint.style.display = 'block';
            const distText = distanceKm != null ? ' (' + distanceKm + ' km)' : '';
            const extraHint = distanceKm == null && routePath !== '' ? ' ' + distanceNeedsSchoolCoordsHint : '';
            routeHint.textContent = routeFromDriverHint + distText + extraHint;
        }
    }

    async function loadDriverAutoFill() {
        const schoolId = schoolSelect.value;
        const tripType = tripTypeSelect.value;
        const driverId = driverSelect.value;

        if (!schoolId || !tripType || !driverId) {
            clearAutoFields();
            return;
        }

        const params = new URLSearchParams({
            school_id: schoolId,
            trip_type: tripType,
            driver_id: driverId,
        });

        try {
            const res = await fetch(autoFillUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                if (routeHint) {
                    routeHint.style.display = 'block';
                    routeHint.textContent = loadFailedHint + ' (' + res.status + ')';
                }
                return;
            }
            const data = await res.json();
            applyAutoFillPayload(data);
        } catch (e) {
            console.error(e);
            if (routeHint) {
                routeHint.style.display = 'block';
                routeHint.textContent = loadFailedHint;
            }
        }
    }

    async function refreshDriverList() {
        const schoolId = schoolSelect.value;
        const tripType = tripTypeSelect.value;

        if (!schoolId || !tripType) {
            setSelectOptions(driverSelect, [], '—', false);
            clearAutoFields();
            return;
        }

        const params = new URLSearchParams({ school_id: schoolId, trip_type: tripType });
        if (exceptTripId) {
            params.set('except_trip_id', String(exceptTripId));
        }

        try {
            const res = await fetch(driversUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            const keepDriver = String(driverSelect.value || '');
            setSelectOptions(driverSelect, data.drivers || [], '—', true);
            if (keepDriver && !(data.drivers || []).some(function (d) { return String(d.id) === keepDriver; })) {
                driverSelect.value = '';
                clearAutoFields();
                return;
            }
            if (driverSelect.value) {
                await loadDriverAutoFill();
            }
        } catch (e) {
            console.error(e);
        }
    }

    schoolSelect.addEventListener('change', function () {
        driverSelect.value = '';
        clearAutoFields();
        refreshDriverList();
    });

    tripTypeSelect.addEventListener('change', function () {
        if (!tripTypeSelect.value) {
            setSelectOptions(driverSelect, [], placeholderShift, false);
            clearAutoFields();
            return;
        }
        driverSelect.value = '';
        clearAutoFields();
        refreshDriverList();
    });

    driverSelect.addEventListener('change', loadDriverAutoFill);

    if (schoolSelect.value && tripTypeSelect.value) {
        refreshDriverList();
    } else if (driverSelect.value && schoolSelect.value && tripTypeSelect.value) {
        loadDriverAutoFill();
    }
});
</script>
