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
    const singleWordMode = @json($studentNameSingleWordMode ?? false);

    let lookupTimer = null;
    let syncingFromSelect = false;
    let syncingFromIdCard = false;
    let pendingParentName = '';

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
            if (row.parent_name) {
                opt.dataset.parentName = row.parent_name;
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
        const parentName = option ? (option.dataset.parentName || '') : '';
        syncingFromSelect = true;
        idCardInput.value = card;
        if (parentName) {
            setParentNameFromGuardian({ parent_name: parentName });
        }
        syncingFromSelect = false;
        setStatus('', null);
    }

    function selectGuardianById(id) {
        guardianSelect.value = String(id);
        syncIdCardFromGuardianSelect();
    }

    function firstWordFrom(value) {
        const words = String(value || '').trim().split(/\s+/).filter(function (word) {
            return word !== '';
        });

        return words[0] || '';
    }

    function enforceStudentFirstNameInput() {
        const fullNameInput = document.getElementById('full_name');
        if (!fullNameInput || !singleWordMode) {
            return;
        }

        if (pendingParentName) {
            const first = firstWordFrom(fullNameInput.value);
            if (first === '') {
                fullNameInput.value = '';
                return;
            }
            fullNameInput.value = first + ' ' + pendingParentName;
            return;
        }

        const first = firstWordFrom(fullNameInput.value);
        if (fullNameInput.value !== first) {
            fullNameInput.value = first;
        }
    }

    function applyStudentFullNameWithParent() {
        enforceStudentFirstNameInput();
    }

    function setParentNameFromGuardian(guardian) {
        const parentName = guardian
            ? String(guardian.parent_name || guardian.full_name || '').trim()
            : '';
        if (!parentName) {
            return;
        }
        pendingParentName = parentName;
        applyStudentFullNameWithParent();
    }

    function fillStudentLocationFromGuardian(guardian) {
        if (!guardian || guardian.home_latitude == null || guardian.home_longitude == null) {
            return;
        }

        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const landmarkInput = document.getElementById('nearest_landmark');

        const lat = Number(guardian.home_latitude).toFixed(7);
        const lng = Number(guardian.home_longitude).toFixed(7);
        const landmark = (landmarkInput && !String(landmarkInput.value || '').trim())
            ? (guardian.home_formatted_address || '')
            : '';

        if (typeof window.studentMapSetLocation === 'function') {
            window.studentMapSetLocation(
                lat,
                lng,
                landmark || guardian.home_formatted_address || '',
            );
            return;
        }

        if (latInput) {
            latInput.value = lat;
        }
        if (lngInput) {
            lngInput.value = lng;
        }
        if (landmarkInput && !String(landmarkInput.value || '').trim() && guardian.home_formatted_address) {
            landmarkInput.value = guardian.home_formatted_address;
        }
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
            setParentNameFromGuardian(guardian);
            fillStudentLocationFromGuardian(guardian);
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
            pendingParentName = '';
            const fullNameInput = document.getElementById('full_name');
            if (fullNameInput && singleWordMode) {
                fullNameInput.value = firstWordFrom(fullNameInput.value);
            }
            setStatus('', null);
            refreshGuardians(false);
        });
    }

    guardianSelect.addEventListener('change', syncIdCardFromGuardianSelect);

    const fullNameInput = document.getElementById('full_name');
    if (fullNameInput && singleWordMode) {
        fullNameInput.addEventListener('input', enforceStudentFirstNameInput);
        fullNameInput.addEventListener('keydown', function (event) {
            if (pendingParentName) {
                return;
            }
            if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
            }
        });
        fullNameInput.addEventListener('paste', function (event) {
            if (pendingParentName) {
                return;
            }
            event.preventDefault();
            const pasted = (event.clipboardData || window.clipboardData).getData('text');
            fullNameInput.value = firstWordFrom(pasted);
        });
    }

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
