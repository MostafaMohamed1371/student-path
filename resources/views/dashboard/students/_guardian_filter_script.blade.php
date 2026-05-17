@if(!empty($formGuardiansUrl))
<script>
(function () {
    const url = @json($formGuardiansUrl);
    const schoolSelect = document.getElementById('student_form_school_id');
    const schoolHidden = document.getElementById('student_form_school_id_hidden');
    const guardianSelect = document.getElementById('student_form_guardian_id');
    if (!url || !guardianSelect) {
        return;
    }

    const placeholderSchool = @json(__('dashboard.student_form_select_school_for_guardians'));
    const placeholderGuardian = @json(__('dashboard.select_guardian'));

    function schoolId() {
        if (schoolSelect && schoolSelect.value) {
            return schoolSelect.value;
        }
        if (schoolHidden && schoolHidden.value) {
            return schoolHidden.value;
        }
        return '';
    }

    function setGuardianOptions(items, keepValue) {
        const prev = keepValue ? String(guardianSelect.value || '') : '';
        guardianSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholderGuardian;
        guardianSelect.appendChild(empty);
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.label;
            if (prev && prev === opt.value) {
                opt.selected = true;
            }
            guardianSelect.appendChild(opt);
        });
    }

    async function refreshGuardians(keepValue) {
        const sid = schoolId();
        if (!sid) {
            guardianSelect.innerHTML = '';
            const hint = document.createElement('option');
            hint.disabled = true;
            hint.textContent = placeholderSchool;
            guardianSelect.appendChild(hint);
            return;
        }

        const params = new URLSearchParams({ school_id: sid });
        const current = guardianSelect.value;
        if (keepValue && current) {
            params.set('include_guardian_id', current);
        }

        try {
            const res = await fetch(url + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            setGuardianOptions(data.guardians || [], keepValue);
        } catch (e) {
            console.error(e);
        }
    }

    if (schoolSelect) {
        schoolSelect.addEventListener('change', function () {
            guardianSelect.value = '';
            refreshGuardians(false);
        });
    }

    refreshGuardians(true);
})();
</script>
@endif
