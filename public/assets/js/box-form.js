(() => {
    const shape = document.getElementById('shape');
    if (!shape) return;
    const width = document.getElementById('width_cm');
    const wrap = document.getElementById('widthWrap');
    const lengthLabel = document.getElementById('lengthLabel');
    const tilt = document.getElementById('can_tilt');
    const hint = document.getElementById('shapeHint');
    function update() {
        const cylinder = shape.value === 'Cylinder';
        wrap.classList.toggle('d-none', cylinder);
        width.required = !cylinder;
        lengthLabel.textContent = cylinder ? 'Diameter (cm)' : 'Length (cm)';
        tilt.disabled = cylinder;
        hint.textContent = cylinder
            ? 'Cylinders are planned upright inside a square footprint equal to their diameter.'
            : 'Measure the outside dimensions of the packed box, including any bulge.';
    }
    shape.addEventListener('change', update);
    update();
})();
