<script>
(function () {
    const url = @json($formOptionsUrl ?? '');
    const schoolSelect = document.getElementById('trip_assign_school_id');
    const driverSelect = document.getElementById('trip_assign_driver_id');
    const tripSelect = document.getElementById('trip_assign_trip_id');
    const studentsSelect = document.getElementById('trip_assign_student_ids');
    const summary = document.getElementById('trip_assign_trip_summary');
    const filterHint = document.getElementById('trip_assign_students_filter_hint');

    if (!url || !schoolSelect || !tripSelect || !studentsSelect) {
        return;
    }

    const placeholderTrip = @json(__('dashboard.trip_assign_select_trip_placeholder'));
    const placeholderDriver = @json(__('dashboard.trip_assign_filter_all_drivers'));
    const routeFilterHint = @json(__('dashboard.trip_students_route_filter_help'));
    const selectTripFirst = @json(__('dashboard.trip_assign_select_trip_placeholder'));

    function schoolId() {
        return String(schoolSelect.value || '');
    }

    function selectedStudentIds() {
        return Array.from(studentsSelect.selectedOptions).map(function (o) {
            return parseInt(o.value, 10);
        }).filter(function (n) { return !isNaN(n); });
    }

    function setTripOptions(items, keepValue) {
        const prev = keepValue ? String(tripSelect.value || '') : '';
        tripSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholderTrip;
        tripSelect.appendChild(empty);
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (prev && prev === opt.value) {
                opt.selected = true;
            }
            tripSelect.appendChild(opt);
        });
    }

    function setDriverOptions(items, keepValue) {
        if (!driverSelect) {
            return;
        }
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
    }

    function setStudentOptions(items, selectedIds) {
        const selected = new Set((selectedIds || []).map(String));
        studentsSelect.innerHTML = '';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (selected.has(opt.value)) {
                opt.selected = true;
            }
            studentsSelect.appendChild(opt);
        });
        studentsSelect.disabled = items.length === 0;
    }

    function updateSummary(trip) {
        if (!summary) {
            return;
        }
        if (!trip) {
            summary.style.display = 'none';
            summary.innerHTML = '';
            return;
        }
        summary.style.display = 'block';
        summary.innerHTML =
            '<p style="margin:0 0 6px;"><strong>{{ __('dashboard.trip_field_type') }}:</strong> ' + (trip.trip_type || '—') + '</p>' +
            '<p style="margin:0 0 6px;"><strong>{{ __('dashboard.route_title') }}:</strong> ' + (trip.route_title || '—') + '</p>' +
            '<p style="margin:0 0 6px;"><strong>{{ __('dashboard.trip_start_time') }}:</strong> ' + (trip.start_time || '—') + '</p>' +
            '<p style="margin:0;"><strong>{{ __('dashboard.students_count') }}:</strong> ' + (trip.students_count ?? 0) + '</p>';
    }

    async function refresh() {
        const sid = schoolId();
        if (!sid) {
            setDriverOptions([], false);
            setTripOptions([], false);
            setStudentOptions([], []);
            updateSummary(null);
            if (filterHint) {
                filterHint.style.display = 'none';
            }
            return;
        }

        const params = new URLSearchParams({ school_id: sid });
        const driverId = driverSelect ? String(driverSelect.value || '') : '';
        if (driverId) {
            params.set('driver_id', driverId);
        }
        const tripId = String(tripSelect.value || '');
        if (tripId) {
            params.set('trip_id', tripId);
        }
        selectedStudentIds().forEach(function (id) {
            params.append('include_student_ids[]', String(id));
        });

        try {
            const res = await fetch(url + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            setDriverOptions(data.drivers || [], true);
            setTripOptions(data.trips || [], true);
            updateSummary(data.trip || null);

            if (tripId) {
                setStudentOptions(data.students || [], data.selected_student_ids || selectedStudentIds());
                if (filterHint) {
                    if (data.route_filter_active && data.corridor_max_km) {
                        filterHint.textContent = routeFilterHint.replace(':km', String(data.corridor_max_km));
                        filterHint.style.display = 'block';
                    } else {
                        filterHint.style.display = 'none';
                    }
                }
            } else {
                setStudentOptions([], []);
                if (filterHint) {
                    filterHint.textContent = selectTripFirst;
                    filterHint.style.display = 'block';
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    if (schoolSelect.tagName === 'SELECT') {
        schoolSelect.addEventListener('change', function () {
            if (driverSelect) {
                driverSelect.value = '';
            }
            tripSelect.value = '';
            refresh();
        });
    }
    if (driverSelect) {
        driverSelect.addEventListener('change', function () {
            tripSelect.value = '';
            refresh();
        });
    }
    tripSelect.addEventListener('change', refresh);
    refresh();
})();
</script>
