@php
    $tripFormPlaceholderSchool = __('dashboard.trip_form_select_school_first');
    $tripFormPlaceholderShift = __('dashboard.trip_form_select_trip_type_for_shift');
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const driversUrl = @json($formOptionsUrl ?? '');
    const autoFillUrl = @json($driverAutoFillUrl ?? '');
    const exceptTripId = @json(isset($exceptTripId) && $exceptTripId ? (int) $exceptTripId : null);
    const oldServiceAreaIds = @json(collect(old('driver_service_area_ids', []))->map(fn ($id) => (string) $id)->values()->all());

    const schoolSelect = document.getElementById('trip_form_school_id');
    const tripTypeSelect = document.getElementById('trip_form_trip_type');
    const driverSelect = document.getElementById('trip_form_driver_id');
    const serviceAreaSelect = document.getElementById('trip_form_driver_service_area_ids');
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
    const routeFromAddressesHint = @json(__('dashboard.trip_route_from_driver_addresses'));
    const routeFromMapHint = @json(__('dashboard.trip_route_from_map'));
    const noRouteForDriverHint = @json(__('dashboard.trip_no_route_for_driver'));
    const distanceNeedsSchoolCoordsHint = @json(__('dashboard.trip_distance_needs_school_coords'));
    const loadFailedHint = @json(__('dashboard.trip_auto_fill_failed'));
    const locationStartToEnd = @json(__('dashboard.trip_location_start_to_end', ['start' => '__START__', 'end' => '__END__']));
    const locationStartOnly = @json(__('dashboard.trip_location_start_only', ['start' => '__START__']));
    const locationEndOnly = @json(__('dashboard.trip_location_end_only', ['end' => '__END__']));

    let lastAutoFill = null;

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

    function locationFromStartAndEnd(start, end) {
        if (start === '' && end === '') {
            return '';
        }
        if (start === '') {
            return locationEndOnly.replace('__END__', end);
        }
        if (end === '') {
            return locationStartOnly.replace('__START__', start);
        }

        return locationStartToEnd.replace('__START__', start).replace('__END__', end);
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

    function setMultiServiceAreaOptions(items, selectedValues) {
        if (!serviceAreaSelect) {
            return;
        }
        const selectedSet = new Set((selectedValues || []).map(String));
        serviceAreaSelect.innerHTML = '';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (selectedSet.has(opt.value)) {
                opt.selected = true;
            }
            serviceAreaSelect.appendChild(opt);
        });
        serviceAreaSelect.disabled = items.length === 0;
    }

    function selectedServiceAreaIds() {
        if (!serviceAreaSelect) {
            return [];
        }

        return Array.from(serviceAreaSelect.selectedOptions)
            .map(function (option) { return String(option.value); })
            .filter(function (value) { return value !== ''; });
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
        if (serviceAreaSelect) {
            serviceAreaSelect.innerHTML = '';
            serviceAreaSelect.disabled = true;
        }
        if (typeof window.tripMapClearSuggestions === 'function') {
            window.tripMapClearSuggestions();
        }
        lastAutoFill = null;
    }

    function averageDistanceForSelectedAreas(rows, endLat, endLng) {
        const distances = [];
        rows.forEach(function (row) {
            if (row.latitude == null || row.longitude == null || endLat == null || endLng == null) {
                return;
            }
            distances.push(haversineKm(parseFloat(row.latitude), parseFloat(row.longitude), endLat, endLng));
        });
        if (distances.length === 0) {
            return null;
        }

        return Math.round((distances.reduce(function (sum, value) { return sum + value; }, 0) / distances.length) * 100) / 100;
    }

    function applyServiceAreaSelection() {
        if (!lastAutoFill || !serviceAreaSelect) {
            return false;
        }

        const selectedIds = selectedServiceAreaIds();
        const rows = (lastAutoFill.service_areas || []).filter(function (row) {
            return selectedIds.includes(String(row.id));
        });

        if (rows.length === 0) {
            if (typeof window.tripMapClearSuggestions === 'function') {
                window.tripMapClearSuggestions();
            }
            return false;
        }

        const titles = rows.map(function (row) { return row.route_title; }).filter(Boolean);

        if (routeTitleInput) {
            routeTitleInput.value = titles.join(' | ');
        }

        if (typeof window.tripMapSetSuggestedMarkers === 'function') {
            window.tripMapSetSuggestedMarkers(rows);
        }

        const startLatInput = document.getElementById('trip_form_start_latitude');
        const hasStart = startLatInput && String(startLatInput.value || '').trim() !== '';
        const firstWithCoords = rows.find(function (row) {
            return row.latitude != null && row.longitude != null;
        });

        if (!hasStart && firstWithCoords && typeof window.tripMapSetStart === 'function') {
            window.tripMapSetStart(
                firstWithCoords.latitude,
                firstWithCoords.longitude,
                firstWithCoords.start_label || firstWithCoords.label || '',
                true,
            );
        } else if (typeof window.tripMapSyncRoutePath === 'function') {
            window.tripMapSyncRoutePath();
        }

        if (routeHint) {
            routeHint.style.display = 'block';
            routeHint.textContent = routeFromAddressesHint;
        }

        return true;
    }

    function applyTransportRouteFields(row) {
        if (routeTitleInput) {
            routeTitleInput.value = row.route_title != null ? String(row.route_title) : '';
        }

        if (
            row.route_start_latitude != null
            && row.route_start_longitude != null
            && typeof window.tripMapSetStart === 'function'
        ) {
            window.tripMapSetStart(
                row.route_start_latitude,
                row.route_start_longitude,
                row.start_address || '',
                true,
            );
        } else if (typeof window.tripMapSyncRoutePath === 'function') {
            window.tripMapSyncRoutePath();
        }

        if (routeHint) {
            routeHint.style.display = 'block';
            const distText = distanceKmInput && distanceKmInput.value !== '0'
                ? ' (' + distanceKmInput.value + ' km)'
                : '';
            routeHint.textContent = routeFromDriverHint + distText;
        }
    }

    function applyAutoFillPayload(row) {
        lastAutoFill = row;

        if (busNumberInput) {
            busNumberInput.value = row.bus_number != null && row.bus_number !== ''
                ? String(row.bus_number)
                : '';
        }

        if (studentsCountInput && row.students_count != null) {
            studentsCountInput.value = String(row.students_count);
        }

        const serviceAreas = row.service_areas || [];
        const preselected = oldServiceAreaIds.length > 0
            ? oldServiceAreaIds
            : serviceAreas.map(function (item) { return String(item.id); });

        setMultiServiceAreaOptions(serviceAreas, preselected);

        if (serviceAreas.length > 0 && applyServiceAreaSelection()) {
            return;
        }

        if (!row.has_route && !row.transport_route_id) {
            if (routeTitleInput) {
                routeTitleInput.value = '';
            }
            if (typeof window.tripMapSyncRoutePath === 'function') {
                window.tripMapSyncRoutePath();
            }
            if (routeHint) {
                routeHint.style.display = 'block';
                routeHint.textContent = serviceAreas.length === 0
                    ? noRouteForDriverHint
                    : routeFromMapHint;
            }

            return;
        }

        applyTransportRouteFields(row);
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

    if (serviceAreaSelect) {
        serviceAreaSelect.addEventListener('change', function () {
            if (!applyServiceAreaSelection() && lastAutoFill) {
                applyTransportRouteFields(lastAutoFill);
            }
        });
    }

    schoolSelect.addEventListener('change', function () {
        driverSelect.value = '';
        clearAutoFields();
        if (typeof window.tripMapSyncSchool === 'function') {
            window.tripMapSyncSchool();
        }
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
