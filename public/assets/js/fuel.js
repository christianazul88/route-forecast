(() => {
    const form = document.getElementById('fuelForm');
    if (!form) return;
    const mode = document.getElementById('fuelMode');
    const method = document.getElementById('returnMethod');
    const manualKm = document.getElementById('manualKm');
    function showReturnFields() {
        const returning = mode.value === 'warehouse';
        const manual = returning && method.value === 'manual';
        document.getElementById('fuelReturnMethod').hidden = !returning || form.dataset.hasTrip === '0';
        document.getElementById('fuelReturnKm').hidden = !manual;
        document.getElementById('fuelReturnEfficiency').hidden = !returning;
        manualKm.required = manual;
        manualKm.disabled = !manual;
        document.getElementById('returnEfficiency').disabled = !returning;
    }
    mode.addEventListener('change', showReturnFields);
    method.addEventListener('change', showReturnFields);
    showReturnFields();
    if (form.dataset.showResult === '1' && window.matchMedia('(max-width: 991.98px)').matches) {
        document.getElementById('fuelResults').focus();
    }
})();
