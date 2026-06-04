@if(!empty($guardianLookupUrl) && empty($guardian))
<script>
(function () {
    const lookupUrl = @json($guardianLookupUrl);
    const idCardInput = document.getElementById('guardian_form_id_card_number');
    const schoolSelect = document.getElementById('guardian_form_school_id');
    const schoolHidden = document.getElementById('guardian_form_school_id_hidden');
    const statusEl = document.getElementById('guardian_form_id_card_status');
    if (!lookupUrl || !idCardInput) {
        return;
    }

    const msgFilled = @json(__('dashboard.guardian_form_id_card_filled', ['name' => '__NAME__']));
    const msgAlreadyAtSchool = @json(__('dashboard.guardian_form_id_card_already_at_school'));
    const msgNotFound = @json(__('dashboard.student_guardian_id_card_not_found'));

    const fields = {
        full_name: document.getElementById('guardian_form_full_name'),
        phone: document.getElementById('guardian_form_phone'),
        backup_phone: document.getElementById('guardian_form_backup_phone'),
        status: document.getElementById('guardian_form_status'),
    };

    let lookupTimer = null;

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
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.style.display = 'none';
            statusEl.textContent = '';
            return;
        }
        statusEl.style.display = 'block';
        statusEl.textContent = message;
        statusEl.style.color = tone === 'error' ? '#b91c1c' : (tone === 'success' ? '#15803d' : '#64748b');
    }

    function fillForm(data) {
        if (fields.full_name && data.full_name) {
            fields.full_name.value = data.full_name;
        }
        if (fields.phone && data.phone) {
            fields.phone.value = data.phone;
        }
        if (fields.backup_phone) {
            fields.backup_phone.value = data.backup_phone || '';
        }
        if (fields.status && data.status) {
            fields.status.value = data.status;
        }
        if (data.id_card_number) {
            idCardInput.value = data.id_card_number;
        }
    }

    async function lookupByIdCard() {
        const normalized = normalizeIdCard(idCardInput.value);
        if (normalized.length < 3) {
            setStatus('', null);
            return;
        }

        const params = new URLSearchParams({ id_card_number: normalized });
        const sid = schoolId();
        if (sid) {
            params.set('school_id', sid);
        }

        try {
            const res = await fetch(lookupUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) {
                return;
            }
            const payload = await res.json();
            if (!payload.found) {
                setStatus(msgNotFound, 'muted');
                return;
            }

            fillForm(payload.guardian || {});

            if (payload.already_at_school) {
                setStatus(msgAlreadyAtSchool, 'error');
                return;
            }

            const name = (payload.guardian && payload.guardian.full_name) ? payload.guardian.full_name : '';
            setStatus(msgFilled.replace('__NAME__', name), 'success');
        } catch (e) {
            console.error(e);
        }
    }

    idCardInput.addEventListener('input', function () {
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookupByIdCard, 400);
    });
    idCardInput.addEventListener('blur', lookupByIdCard);

    if (schoolSelect) {
        schoolSelect.addEventListener('change', function () {
            if (normalizeIdCard(idCardInput.value).length >= 3) {
                lookupByIdCard();
            }
        });
    }
})();
</script>
@endif
