const byId = id => document.getElementById(id);
const openings = byId('truckOpenings');
const status = byId('openingMessage');
let nextOpening = 0;

function renumber() {
    [...openings.children].forEach((row, index) => {
        row.querySelector('legend').textContent = `Opening ${index + 1}`;
    });
    byId('addTruckOpening').disabled = openings.children.length >= 8;
    byId('suggestTruckOpenings').disabled = openings.children.length > 0;
}

function addOpening(values = {}) {
    if (openings.children.length >= 8) return;
    const row = byId('truckOpeningTemplate').content.firstElementChild.cloneNode(true);
    for (const input of row.querySelectorAll('[data-field]')) {
        const field = input.dataset.field;
        input.name = `openings[${nextOpening}][${field}]`;
        input.value = values[field] ?? (field === 'side' ? 'rear' : ['sill', 'offset'].includes(field) ? '0' : '');
    }
    nextOpening++;
    row.querySelector('.remove-opening').addEventListener('click', () => {
        row.remove();
        renumber();
    });
    openings.append(row);
    renumber();
}

for (const opening of JSON.parse(openings.dataset.rows)) addOpening(opening);
renumber();
byId('addTruckOpening').addEventListener('click', () => addOpening());
byId('useTruckPreset').addEventListener('click', () => {
    const preset = byId('truckPreset').value;
    if (preset) byId('cargo_length_ft').value = preset;
});
byId('suggestTruckOpenings').addEventListener('click', () => {
    const type = byId('body_type').value;
    const length = Number(byId('cargo_length_ft').value);
    const width = Number(byId('cargo_width_ft').value);
    const height = Number(byId('cargo_height_ft').value);
    if (!type || Math.min(length, width, height) <= 0) {
        status.textContent = 'Choose a body type and enter the interior dimensions first.';
        return;
    }
    const sides = type === 'Wing van' ? ['left', 'right']
        : type.endsWith('dropside') ? ['left', 'right', 'rear'] : ['rear'];
    sides.forEach(side => addOpening({side, width: side === 'rear' ? width : length, height}));
    status.textContent = 'Confirm which openings work on this truck and adjust their measurements before saving.';
});
