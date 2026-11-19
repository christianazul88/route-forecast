import {createLoadState, cloneLoadState, packInto} from './load-packing.mjs?v=3d1';
import {createLoadView, renderLoadFloor} from './load-view.mjs';

const root = document.getElementById('loadPlanner');
if (root) {
    const trucks = JSON.parse(root.dataset.trucks);
    const boxes = JSON.parse(root.dataset.boxes);
    const truckById = new Map(trucks.map(truck => [String(truck.id), truck]));
    const boxById = new Map(boxes.map(box => [String(box.id), box]));
    const truckSelect = document.getElementById('loadTruck');
    const rows = document.getElementById('loadRows');
    const message = document.getElementById('loadMessage');
    const number = new Intl.NumberFormat('en-PH', {maximumFractionDigits: 3});
    const colors = ['#147d86', '#e39b37', '#6f73bb', '#d36d73', '#4f9b67', '#8d74a7', '#667f93'];
    const control = id => document.getElementById(id);
    const view3d = control('view3d');
    const viewFloor = control('viewFloor');
    const level = control('cargoLevel');
    const placement = control('cargoPlacement');
    const yaw = control('cargoTurn');
    const elevation = control('cargoTilt');
    let scene;
    let layout;
    let nextRowId = 1;
    let timer;
    let initializing = true;

    function say(text, type = 'info') {
        message.className = `alert alert-${type}`;
        message.textContent = text;
    }

    function dimensions(box) {
        return box.shape === 'Cylinder'
            ? `Ø${number.format(box.length)} × ${number.format(box.height)} cm`
            : `${number.format(box.length)} × ${number.format(box.width)} × ${number.format(box.height)} cm`;
    }

    function showTruck() {
        const truck = truckById.get(truckSelect.value);
        const target = document.getElementById('truckMeasurements');
        const isCustom = truckSelect.value === 'custom';
        document.getElementById('customTruckFields').classList.toggle('d-none', !isCustom);
        if (isCustom) {
            target.textContent = 'Enter this vehicle’s measured dimensions and payload below. These values are used only in this estimate.';
        } else if (!truck) {
            target.textContent = 'Select a truck to see its usable space.';
        } else if (truck.length == null || truck.width == null || truck.height == null) {
            target.textContent = `${truck.bodyType || 'Body type not set'} · No usable cargo measurements yet. Ask an admin to enter them in Trucks.`;
        } else {
            target.textContent = `${truck.bodyType || 'Body type not set'} · Usable cargo space: ${number.format(truck.length)} × ${number.format(truck.width)} × ${number.format(truck.height)} cm · ${number.format(truck.capacity)} kg payload${truck.doorWidth == null ? ' · loading opening not measured' : ` · loading opening ${number.format(truck.doorWidth)} × ${number.format(truck.doorHeight)} cm`}.`;
        }
    }

    function readTruck() {
        if (truckSelect.value !== 'custom') return truckById.get(truckSelect.value);
        const value = id => Number(document.getElementById(id).value);
        const length = value('customTruckLength');
        const width = value('customTruckWidth');
        const height = value('customTruckHeight');
        const capacity = value('customTruckCapacity');
        if (![length, width, height, capacity].every(n => Number.isFinite(n) && n > 0)
            || Math.max(length, width, height) > 2000 || capacity > 99999999.99) {
            throw new Error('Enter positive interior dimensions and payload for the custom truck.');
        }
        const doorWidthText = document.getElementById('customDoorWidth').value;
        const doorHeightText = document.getElementById('customDoorHeight').value;
        if (Boolean(doorWidthText) !== Boolean(doorHeightText)) {
            throw new Error('Enter both door measurements, or leave both blank.');
        }
        const doorWidth = doorWidthText ? Number(doorWidthText) : null;
        const doorHeight = doorHeightText ? Number(doorHeightText) : null;
        if (doorWidth !== null && (!Number.isFinite(doorWidth) || !Number.isFinite(doorHeight)
            || doorWidth <= 0 || doorHeight <= 0 || doorWidth > width || doorHeight > height)) {
            throw new Error('The door opening must be positive and no larger than the interior.');
        }
        return {length, width, height, capacity, doorWidth, doorHeight};
    }

    function schedule() {
        if (initializing) return;
        clearTimeout(timer);
        timer = setTimeout(calculate, 200);
    }

    function addRow() {
        const element = document.getElementById('loadRowTemplate').content.firstElementChild.cloneNode(true);
        element.dataset.rowId = String(nextRowId++);
        const select = element.querySelector('.load-box-select');
        select.value = boxes.length ? String(boxes[0].id) : 'custom';
        const details = element.querySelector('.load-box-details');
        const custom = element.querySelector('.load-custom-fields');
        const shape = element.querySelector('.custom-shape');
        function updateShape() {
            const cylinder = shape.value === 'Cylinder';
            element.querySelector('.custom-width-wrap').classList.toggle('d-none', cylinder);
            element.querySelector('.custom-length-label').textContent = cylinder ? 'Diameter (cm)' : 'Length (cm)';
            element.querySelector('.custom-tilt').disabled = cylinder;
            element.querySelector('.custom-shape-hint').textContent = shape.value === 'Irregular'
                ? 'Use the smallest rectangular space that encloses the item. The space directly above it stays clear.'
                : '';
            schedule();
        }
        function updateDetails() {
            const box = boxById.get(select.value);
            const isCustom = select.value === 'custom';
            custom.classList.toggle('d-none', !isCustom);
            details.textContent = isCustom ? 'Enter outside dimensions and weight for this one-off item.'
                : box ? `${dimensions(box)} · ${number.format(box.weight)} kg each${box.canTilt ? ' · may be tilted' : ' · upright only'}${box.shape === 'Irregular' ? ' · floor only' : ''}` : '';
            schedule();
        }
        select.addEventListener('change', updateDetails);
        shape.addEventListener('change', updateShape);
        custom.querySelectorAll('input, select').forEach(input => input.addEventListener('input', schedule));
        element.querySelector('.load-quantity').addEventListener('input', schedule);
        element.querySelector('.load-remove').addEventListener('click', () => { element.remove(); schedule(); });
        rows.append(element);
        updateShape();
        updateDetails();
    }

    function readBox(element) {
        const selected = element.querySelector('.load-box-select').value;
        if (selected !== 'custom') return boxById.get(selected);
        const value = selector => Number(element.querySelector(selector).value);
        const shape = element.querySelector('.custom-shape').value;
        const length = value('.custom-length');
        const width = shape === 'Cylinder' ? length : value('.custom-width');
        const height = value('.custom-height');
        const weight = value('.custom-weight');
        if (![length, width, height, weight].every(n => Number.isFinite(n) && n > 0)
            || Math.max(length, width, height) > 2000 || weight > 9999999.999) {
            throw new Error('Enter positive dimensions and weight for every custom item.');
        }
        const box = {
            id: `custom-${element.dataset.rowId}`,
            name: element.querySelector('.custom-name').value.trim() || `Custom ${shape.toLowerCase()} ${element.dataset.rowId}`,
            shape, length, width, height, weight,
            canTilt: shape !== 'Cylinder' && element.querySelector('.custom-tilt').checked,
        };
        boxById.set(String(box.id), box);
        return box;
    }

    function addCell(row, value, className = '') {
        const cell = document.createElement('td');
        cell.textContent = value;
        if (className) cell.className = className;
        row.append(cell);
    }

    function renderLayout(state) {
        const legend = control('floorLegend');
        legend.replaceChildren();
        const usedIds = [...new Set(state.placements.map(row => row.boxId))];
        const colorById = new Map(usedIds.map((id, index) => [id, colors[index % colors.length]]));
        const packages = new Map(usedIds.map(id => [id, boxById.get(String(id))]));
        for (const id of usedIds) {
            const box = packages.get(id);
            const item = document.createElement('span');
            item.className = 'me-3 d-inline-block';
            const swatch = document.createElement('span');
            swatch.className = 'load-swatch';
            swatch.style.backgroundColor = colorById.get(id);
            item.append(swatch, document.createTextNode(box.name + (box.shape === 'Irregular' ? ' (reserved space)' : '')));
            legend.append(item);
        }
        layout = {state, colorById, packages};
        renderLoadFloor(control('loadFloor'), state, colorById);
        const currentLevel = level.value;
        const heights = [...new Set(state.placements.map(row => Math.round(row.z * 1e6) / 1e6))].sort((a, b) => a - b);
        level.replaceChildren(new Option('All levels', ''),
            ...heights.map(height => new Option(height === 0 ? 'Floor layer' : `${number.format(height)} cm above floor`, height)));
        level.value = heights.some(height => String(height) === currentLevel) ? currentLevel : '';
        level.disabled = heights.length < 2;
        if (scene === undefined) {
            try {
                scene = createLoadView(control('loadCanvas'), control('cargoCab'), control('cargoRear'));
            } catch {
                scene = null;
            }
            if (!scene) {
                view3d.disabled = true;
                showView(false);
                control('cargoViewNote').textContent = '3D is unavailable on this browser. The floor view is shown below.';
                control('loadFloor').before(control('cargoViewNote'));
            }
        }
        control('cargoPlaceholder').hidden = true;
        control('cargoControls').hidden = false;
        updatePlacements();
    }

    function updatePlacements() {
        if (!layout) return;
        const {state, packages} = layout;
        const limit = level.value === '' ? Infinity : Number(level.value) + .000001;
        const current = placement.value;
        const options = [new Option('Choose a row of packages', '')];
        for (const [index, row] of state.placements.entries()) {
            if (row.z > limit) continue;
            const name = packages.get(row.boxId).name;
            options.push(new Option(`${index + 1}. ${name} × ${row.count} at ${number.format(row.z)} cm high`, index));
        }
        placement.replaceChildren(...options);
        placement.value = options.some(option => option.value === current) ? current : '';
        control('cargoInspector').hidden = options.length < 2;
        updateScene();
    }

    function updateScene() {
        if (!layout) return;
        const {state, colorById, packages} = layout;
        const limit = level.value === '' ? Infinity : Number(level.value) + .000001;
        const visible = state.placements.filter(row => row.z <= limit);
        const row = placement.value === '' ? null : state.placements[Number(placement.value)];
        if (scene) {
            const grouped = scene.setLoad(state, visible, colorById, packages, row);
            control('cargoViewNote').textContent = grouped ? 'Large loads show adjoining items grouped into rows.' : '';
        }
        control('cargoPlacementDetail').textContent = row
            ? `Start in usable cargo space: ${number.format(row.x)} cm from the cab end, ${number.format(row.y)} cm from the reference side, ${number.format(row.z)} cm above the floor. Each item occupies ${[row.l, row.w, row.h].map(n => number.format(n)).join(' × ')} cm along the truck’s length, width and height.`
            : '';
    }

    function showView(use3d) {
        control('cargo3d').hidden = !use3d;
        control('cargoFloor').hidden = use3d;
        view3d.classList.toggle('active', use3d);
        viewFloor.classList.toggle('active', !use3d);
        view3d.setAttribute('aria-pressed', String(use3d));
        viewFloor.setAttribute('aria-pressed', String(!use3d));
        if (use3d) scene?.redraw();
    }

    function calculate() {
        showTruck();
        let truck;
        try { truck = readTruck(); }
        catch (error) { say(error.message, 'warning'); return; }
        if (!truck) { say('Choose a truck to estimate a load.'); return; }
        const clearance = Number(document.getElementById('wallClearance').value);
        let state;
        try {
            state = createLoadState(truck, clearance);
        } catch (error) {
            say(error.message, 'warning');
            return;
        }
        const entries = [];
        for (const element of rows.children) {
            let box;
            try { box = readBox(element); }
            catch (error) { say(error.message, 'warning'); return; }
            const quantity = Number(element.querySelector('.load-quantity').value);
            if (!box || !Number.isSafeInteger(quantity) || quantity < 1 || quantity > 10000) {
                say('Choose a package and a whole quantity from 1 to 10,000 in every row.', 'warning');
                return;
            }
            entries.push({rowId: element.dataset.rowId, box, quantity});
        }
        const results = new Map();
        const volume = box => box.length * box.width * box.height;
        const sorted = [...entries].sort((a, b) =>
            Number(b.box.shape === 'Irregular') - Number(a.box.shape === 'Irregular') || volume(b.box) - volume(a.box));
        for (const entry of sorted) {
            results.set(entry.rowId, packInto(state, entry.box, entry.quantity));
        }
        const requested = entries.reduce((sum, entry) => sum + entry.quantity, 0);
        document.getElementById('placedMetric').textContent = number.format(state.placed);
        document.getElementById('placedSub').textContent = `of ${number.format(requested)} requested`;
        document.getElementById('weightMetric').textContent = `${number.format(state.weight)} kg`;
        document.getElementById('weightSub').textContent = `of ${number.format(state.capacity)} kg · ${number.format(Math.max(0, state.capacity - state.weight))} kg left`;
        const percent = Math.min(100, 100 * state.envelopeVolume / state.volume);
        document.getElementById('spacePercent').textContent = `${number.format(percent)}%`;
        document.getElementById('spaceBar').style.width = `${percent}%`;
        document.getElementById('spaceText').textContent = `${number.format(state.envelopeVolume / 1_000_000)} m³ of ${number.format(state.volume / 1_000_000)} m³ reserved by package footprints. Open pockets may be too small for another box.`;
        renderLayout(state);

        const plannedBody = document.getElementById('plannedResult');
        plannedBody.replaceChildren();
        for (const entry of entries) {
            const result = results.get(entry.rowId);
            const row = document.createElement('tr');
            addCell(row, entry.box.name);
            addCell(row, number.format(entry.quantity), 'text-end');
            addCell(row, number.format(result.placed), 'text-end fw-semibold');
            addCell(row, result.reason || 'Estimated fit');
            plannedBody.append(row);
        }
        if (!entries.length) {
            const row = document.createElement('tr');
            addCell(row, 'No planned goods. Extra quantities below show an empty truck.');
            row.firstChild.colSpan = 4;
            plannedBody.append(row);
        }

        const extraBody = document.getElementById('extraResult');
        extraBody.replaceChildren();
        const candidates = [...boxes, ...entries.filter(entry => String(entry.box.id).startsWith('custom-')).map(entry => entry.box)];
        for (const box of candidates) {
            const copy = cloneLoadState(state);
            const theoretical = Math.floor((copy.volume - copy.envelopeVolume + 1e-7) / (box.length * box.width * box.height));
            const limit = Math.max(0, Math.min(10000, theoretical));
            const extra = packInto(copy, box, limit, 4000, false).placed;
            const row = document.createElement('tr');
            addCell(row, box.name);
            addCell(row, dimensions(box));
            addCell(row, extra === 10000 ? '10,000+' : number.format(extra), 'text-end fw-semibold');
            extraBody.append(row);
        }
        if (!candidates.length) {
            const row = document.createElement('tr');
            addCell(row, 'Add box types to see extra capacity.');
            row.firstChild.colSpan = 3;
            extraBody.append(row);
        }
        say(state.placed === requested
            ? 'All requested goods fit in this estimate. Check the extra capacity options below.'
            : `${number.format(requested - state.placed)} requested items could not be placed. Review dimensions, payload, and door opening.`,
            state.placed === requested ? 'success' : 'warning');
    }

    document.getElementById('addLoadRow').addEventListener('click', addRow);
    document.getElementById('calculateLoad').addEventListener('click', calculate);
    document.getElementById('wallClearance').addEventListener('input', schedule);
    document.querySelectorAll('.custom-truck-field').forEach(input => input.addEventListener('input', schedule));
    truckSelect.addEventListener('change', schedule);
    view3d.addEventListener('click', () => showView(true));
    viewFloor.addEventListener('click', () => showView(false));
    level.addEventListener('change', updatePlacements);
    placement.addEventListener('change', updateScene);
    const rotate = () => scene?.rotate(Number(yaw.value), Number(elevation.value));
    yaw.addEventListener('input', rotate);
    elevation.addEventListener('input', rotate);
    control('cargoReset').addEventListener('click', () => {
        yaw.value = yaw.defaultValue;
        elevation.value = elevation.defaultValue;
        rotate();
    });
    showTruck();
    addRow();
    initializing = false;
    if (truckSelect.value && truckSelect.value !== 'custom') schedule();
}
