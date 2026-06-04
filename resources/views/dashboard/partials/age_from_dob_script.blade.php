<script>
(function () {
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');
    if (!dobInput || !ageInput) {
        return;
    }

    function yearsFromBirthDate(isoDate) {
        const parts = isoDate.split('-').map(Number);
        if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
            return null;
        }
        const birth = new Date(parts[0], parts[1] - 1, parts[2]);
        const today = new Date();
        let years = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            years -= 1;
        }

        return years >= 0 ? years : null;
    }

    function syncAgeFromDob() {
        const value = dobInput.value;
        if (!value) {
            return;
        }
        const years = yearsFromBirthDate(value);
        if (years !== null) {
            ageInput.value = String(years);
        }
    }

    dobInput.addEventListener('change', syncAgeFromDob);
    dobInput.addEventListener('input', syncAgeFromDob);
    syncAgeFromDob();
})();
</script>
