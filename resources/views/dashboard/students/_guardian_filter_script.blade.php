@if(!empty($formGuardiansUrl))
<script>
(function () {
    const url = @json($formGuardiansUrl);
    const lookupUrl = @json($guardianLookupUrl ?? '');
    const schoolSelect = document.getElementById('student_form_school_id');
    const schoolHidden = document.getElementById('student_form_school_id_hidden');
    const guardianSelect = document.getElementById('student_form_guardian_id');
    const idCardInput = document.getElementById('student_form_guardian_id_card');
    const idCardStatus = document.getElementById('student_form_guardian_id_card_status');
    if (!url || !guardianSelect) {
        return;
    }

    const placeholderSchool = @json(__('dashboard.student_form_select_school_for_guardians'));
    const placeholderGuardian = @json(__('dashboard.select_guardian'));
    const msgFoundTemplate = @json(__('dashboard.student_guardian_id_card_lookup_found', ['name' => '__NAME__']));
    const msgProvisioned = @json(__('dashboard.student_guardian_id_card_provisioned', ['name' => '__NAME__']));
    const msgNotFound = @json(__('dashboard.student_guardian_id_card_not_found'));

    let lookupTimer = null;
    let syncingFromSelect = false;
    let syncingFromIdCard = false;

    function schoolId() {
        if (schoolSelect && schoolSelect.value) {
            return schoolSelect.value;
        }
        if (schoolHidden && schoolHidden.value) {
            return schoolHidden.value;
        }
        return '';
    }

    function normalizeIdCard(value) {
        return String(value || '').replace(/\s+/g, '').toUpperCase();
    }

    function setStatus(message, tone) {
        if (!idCardStatus) {
            return;
        }
        if (!message) {
            idCardStatus.style.display = 'none';
            idCardStatus.textContent = '';
            return;
        }
        idCardStatus.style.display = 'block';
        idCardStatus.textContent = message;
        idCardStatus.style.color = tone === 'error' ? '#b91c1c' : (tone === 'success' ? '#15803d' : '#64748b');
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
            if (row.id_card_number) {
                opt.dataset.idCard = row.id_card_number;
            }
            if (prev && prev === opt.value) {
                opt.selected = true;
            }
            guardianSelect.appendChild(opt);
        });
        syncIdCardFromGuardianSelect();
    }

    function syncIdCardFromGuardianSelect() {
        if (!idCardInput || syncingFromIdCard) {
            return;
        }
        const option = guardianSelect.selectedOptions[0];
        const card = option ? (option.dataset.idCard || '') : '';
        syncingFromSelect = true;
        idCardInput.value = card;
        syncingFromSelect = false;
        setStatus('', null);
    }

    function selectGuardianById(id) {
        guardianSelect.value = String(id);
        syncIdCardFromGuardianSelect();
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

    async function lookupGuardianByIdCard() {
        if (!lookupUrl || !idCardInput || syncingFromSelect) {
            return;
        }

        const normalized = normalizeIdCard(idCardInput.value);
        if (normalized.length < 3) {
            setStatus('', null);
            return;
        }

        const sid = schoolId();
        if (!sid) {
            setStatus(placeholderSchool, 'muted');
            return;
        }

        const params = new URLSearchParams({
            school_id: sid,
            id_card_number: normalized,
            ensure_for_school: '1',
        });

        try {
            const res = await fetch(lookupUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            if (!data.found) {
                setStatus(msgNotFound, 'muted');
                return;
            }

            const guardian = data.guardian || {};
            if (!guardian.school_matches) {
                setStatus(msgNotFound, 'muted');
                return;
            }

            syncingFromIdCard = true;
            await refreshGuardians(true);
            selectGuardianById(guardian.id);
            idCardInput.value = guardian.id_card_number || normalized;
            syncingFromIdCard = false;

            const statusMsg = data.provisioned_for_school
                ? msgProvisioned.replace('__NAME__', guardian.full_name || '')
                : msgFoundTemplate.replace('__NAME__', guardian.full_name || '');
            setStatus(statusMsg, 'success');
        } catch (e) {
            console.error(e);
        }
    }

    if (schoolSelect) {
        schoolSelect.addEventListener('change', function () {
            guardianSelect.value = '';
            if (idCardInput) {
                idCardInput.value = '';
            }
            setStatus('', null);
            refreshGuardians(false);
        });
    }

    guardianSelect.addEventListener('change', syncIdCardFromGuardianSelect);

    if (idCardInput) {
        idCardInput.addEventListener('input', function () {
            if (syncingFromSelect) {
                return;
            }
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(lookupGuardianByIdCard, 400);
        });
        idCardInput.addEventListener('blur', lookupGuardianByIdCard);
    }

    refreshGuardians(true);
})();
</script>
@endif
