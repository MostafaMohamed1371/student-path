@if(!empty($formOptionsUrl) && !empty($formStudentsUrl))
<script>
(function () {
    const form = document.getElementById('trip_request_create_form');
    const formOptionsUrl = @json($formOptionsUrl);
    const formStudentsUrl = @json($formStudentsUrl);
    const schoolSelect = document.getElementById('trip_request_school_id');
    const tripSelect = document.getElementById('trip_request_trip_history_id');
    const userSelect = document.getElementById('trip_request_user_id');
    const studentSelect = document.getElementById('trip_request_student_id');

    if (!form || !schoolSelect || !tripSelect || !userSelect || !studentSelect) {
        return;
    }

    const oldSchool = @json(old('school_id'));
    const oldTrip = @json(old('trip_history_id'));
    const oldUser = @json(old('user_id'));
    const oldStudent = @json(old('student_id'));

    const placeholderSchool = @json(__('dashboard.trip_request_select_school_first'));
    const placeholderParent = @json(__('dashboard.select_parent_user'));
    const placeholderStudent = @json(__('dashboard.trip_request_select_parent_first'));
    const placeholderTrip = @json(__('dashboard.select_trip'));
    const placeholderNoParents = @json(__('dashboard.trip_request_no_parents_for_school'));
    const placeholderNoStudents = @json(__('dashboard.trip_request_no_students_for_parent'));
    const placeholderNoTrips = @json(__('dashboard.trip_request_no_trips_for_school'));
    const placeholderLoading = @json(__('dashboard.loading'));
    const msgIncomplete = @json(__('dashboard.trip_request_form_incomplete'));

    function setSelectOptions(select, items, placeholder, selectedValue) {
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (selectedValue && String(selectedValue) === opt.value) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    function resetStudentSelect(message) {
        setSelectOptions(studentSelect, [], message || placeholderStudent, null);
    }

    function resetParentAndTrip() {
        setSelectOptions(userSelect, [], placeholderSchool, null);
        setSelectOptions(tripSelect, [], placeholderSchool, null);
        resetStudentSelect(placeholderStudent);
    }

    async function loadSchoolOptions(keepSelections) {
        const schoolId = schoolSelect.value;
        if (!schoolId) {
            resetParentAndTrip();
            return;
        }

        resetStudentSelect(placeholderStudent);
        setSelectOptions(userSelect, [], placeholderLoading, null);
        setSelectOptions(tripSelect, [], placeholderLoading, null);

        const params = new URLSearchParams({ school_id: schoolId });
        try {
            const res = await fetch(formOptionsUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                resetParentAndTrip();
                return;
            }
            const data = await res.json();
            const parents = data.parents || [];
            const trips = data.trips || [];

            setSelectOptions(
                userSelect,
                parents,
                parents.length ? placeholderParent : placeholderNoParents,
                keepSelections ? oldUser : null,
            );
            setSelectOptions(
                tripSelect,
                trips,
                trips.length ? placeholderTrip : placeholderNoTrips,
                keepSelections ? oldTrip : null,
            );

            if (keepSelections && oldUser) {
                await loadStudents(true);
            } else {
                resetStudentSelect(placeholderStudent);
            }
        } catch (e) {
            console.error(e);
            resetParentAndTrip();
        }
    }

    async function loadStudents(keepSelection) {
        const schoolId = schoolSelect.value;
        const userId = userSelect.value;
        if (!schoolId || !userId) {
            resetStudentSelect(placeholderStudent);
            return;
        }

        setSelectOptions(studentSelect, [], placeholderLoading, null);

        const params = new URLSearchParams({
            school_id: schoolId,
            user_id: userId,
        });

        try {
            const res = await fetch(formStudentsUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                resetStudentSelect(placeholderNoStudents);
                return;
            }
            const data = await res.json();
            const students = data.students || [];
            setSelectOptions(
                studentSelect,
                students,
                students.length ? @json(__('dashboard.select_student')) : placeholderNoStudents,
                keepSelection ? oldStudent : null,
            );
        } catch (e) {
            console.error(e);
            resetStudentSelect(placeholderNoStudents);
        }
    }

    schoolSelect.addEventListener('change', function () {
        loadSchoolOptions(false);
    });

    userSelect.addEventListener('change', function () {
        loadStudents(false);
    });

    form.addEventListener('submit', function (e) {
        if (!schoolSelect.value || !tripSelect.value || !userSelect.value || !studentSelect.value) {
            e.preventDefault();
            window.alert(msgIncomplete);
        }
    });

    if (schoolSelect.value) {
        loadSchoolOptions(true);
    }
})();
</script>
@endif
