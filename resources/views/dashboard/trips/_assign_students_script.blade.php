<script>
(function () {
    const url = @json($formOptionsUrl ?? '');
    const schoolSelect = document.getElementById('trip_assign_school_id');
    const driverSelect = document.getElementById('trip_assign_driver_id');
    const tripSelect = document.getElementById('trip_assign_trip_ids');
    const studentsSelect = document.getElementById('trip_assign_student_ids');
    const summary = document.getElementById('trip_assign_trip_summary');
    const filterHint = document.getElementById('trip_assign_students_filter_hint');

    if (!url || !schoolSelect || !tripSelect || !studentsSelect) {
        return;
    }

    const routeFilterHint = @json(__('dashboard.trip_students_route_filter_help'));
    const selectTripFirst = @json(__('dashboard.trip_assign_select_trip_placeholder'));
    const tripTypeLabel = @json(__('dashboard.trip_field_type'));
    const tripStartLabel = @json(__('dashboard.trip_start_time'));
    const studentsCountLabel = @json(__('dashboard.students_count'));

    function schoolId() {
        return String(schoolSelect.value || '');
    }

    function selectedTripIds() {
        return Array.from(tripSelect.selectedOptions).map(function (o) {
            return parseInt(o.value, 10);
        }).filter(function (n) { return !isNaN(n); });
    }

    function selectedStudentIds() {
        return Array.from(studentsSelect.selectedOptions).map(function (o) {
            return parseInt(o.value, 10);
        }).filter(function (n) { return !isNaN(n); });
    }

    function setTripOptions(items, keepSelection) {
        const selected = new Set(keepSelection ? selectedTripIds().map(String) : []);
        tripSelect.innerHTML = '';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (selected.has(opt.value)) {
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
        empty.textContent = @json(__('dashboard.trip_assign_filter_all_drivers'));
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

    function updateSummary(trips) {
        if (!summary) {
            return;
        }
        if (!trips || trips.length === 0) {
            summary.style.display = 'none';
            summary.innerHTML = '';
            return;
        }
        summary.style.display = 'block';
        summary.innerHTML = trips.map(function (trip, index) {
            const border = index < trips.length - 1 ? 'border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:12px;' : '';
            return '<div style="' + border + '">' +
                '<p style="margin:0 0 6px;"><strong>#' + trip.id + '</strong> — ' + (trip.route_title || '—') + '</p>' +
                '<p style="margin:0 0 6px;"><strong>' + tripTypeLabel + ':</strong> ' + (trip.trip_type || '—') + '</p>' +
                '<p style="margin:0 0 6px;"><strong>' + tripStartLabel + ':</strong> ' + (trip.start_time || '—') + '</p>' +
                '<p style="margin:0;"><strong>' + studentsCountLabel + ':</strong> ' + (trip.students_count ?? 0) + '</p>' +
                '</div>';
        }).join('');
    }

    async function refresh() {
        const sid = schoolId();
        if (!sid) {
            setDriverOptions([], false);
            setTripOptions([], false);
            setStudentOptions([], []);
            updateSummary([]);
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
        selectedTripIds().forEach(function (id) {
            params.append('trip_ids[]', String(id));
        });
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
            updateSummary(data.selected_trips || []);

            if (selectedTripIds().length > 0) {
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
            Array.from(tripSelect.options).forEach(function (opt) {
                opt.selected = false;
            });
            refresh();
        });
    }
    if (driverSelect) {
        driverSelect.addEventListener('change', function () {
            Array.from(tripSelect.options).forEach(function (opt) {
                opt.selected = false;
            });
            refresh();
        });
    }
    tripSelect.addEventListener('change', refresh);

    const form = document.getElementById('trip_assign_form');
    if (form) {
        form.addEventListener('submit', function () {
            if (studentsSelect.disabled) {
                studentsSelect.disabled = false;
            }
            if (tripSelect.disabled) {
                tripSelect.disabled = false;
            }
        });
    }

    refresh();
})();
</script>
