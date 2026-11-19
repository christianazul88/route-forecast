import {cargoBay, doorLabel, loadingOrder} from './stop-packing.mjs?v=load2';
import {createLoadView, renderLoadFloor} from './load-view.mjs?v=load2';

const get = id => document.getElementById(id);
const root = get('loadPlanner');
const trucks = JSON.parse(root.dataset.trucks);
const catalog = JSON.parse(root.dataset.boxes);
const trip = JSON.parse(root.dataset.trip);
const demo = JSON.parse(root.dataset.demo);
const rows = get('loadRows');
const truckSelect = get('loadTruck');
const saveButton = get('saveLoad');
const capacityCheck = get('capacityCheck');
const number = new Intl.NumberFormat('en-PH', {maximumFractionDigits: 2});
const CM_PER_FOOT = 30.48;
const fixedColors = ['#157c86', '#dd8434', '#6876c1', '#c4566c', '#4c9a5b', '#8a62ac', '#a7872c', '#3e88ba'];
const deliveryStops = trip?.stops ?? (demo ? [
    {id: 1, order: 1, name: 'First delivery'},
    {id: 2, order: 2, name: 'Second delivery'},
] : [{id: 1, order: 1, name: 'Single delivery'}]);
const stopById = new Map(deliveryStops.map(stop => [stop.id, stop]));
const stopName = stop => `${stop.order}. ${stop.name}`;
const feet = cm => number.format(cm / CM_PER_FOOT);
const fieldNumber = (id, multiplier = 1) => Number(get(id).value) * multiplier;
const rowNodes = () => [...rows.children];
let nextId = 1;
let revision = trip?.saved?.revision ?? 0;
let worker;
let workerReject;
let current;
let scene;
let onlyFloor = false;
let displayed = [];
let allowedDoors = {};
let saving = false;

function say(text, state = 'info') {
    const message = get('loadMessage');
    message.textContent = text;
    message.className = `alert alert-${state}`;
}

function el(tag, text, classes) {
    const node = document.createElement(tag);
    if (text) node.textContent = text;
    if (classes) node.className = classes;
    return node;
}

function updateActions() {
    capacityCheck.disabled = !current;
    if (saveButton) saveButton.disabled = saving || !current?.result.placed
        || !current.inputs.truck.confirmed || !current.inputs.truck.openings.length;
}

function cancelWork() {
    worker?.terminate();
    worker = null;
    workerReject?.(new DOMException('Estimate superseded', 'AbortError'));
    workerReject = null;
}

function runWorker(data) {
    cancelWork();
    return new Promise((resolve, reject) => {
        workerReject = reject;
        try {
            const task = new Worker(new URL('./load-worker.mjs?v=load2', import.meta.url), {type: 'module'});
            worker = task;
            const finish = event => {
                if (worker !== task) return;
                task.terminate();
                worker = null;
                workerReject = null;
                if (event.data?.error) reject(new Error(event.data.error));
                else if (event.data) resolve(event.data.value);
                else reject(new Error('The calculation could not run. Refresh and try again.'));
            };
            task.onmessage = finish;
            task.onerror = finish;
            task.postMessage(data);
        } catch (error) {
            worker = null;
            workerReject = null;
            reject(error);
        }
    });
}

function invalidate() {
    cancelWork();
    if (current) say('Inputs changed. Estimate again to update the results.');
    current = null;
    get('calculateLoad').disabled = false;
    get('extraResult').textContent = '';
    updateActions();
}

function readTruck() {
    if (truckSelect.value !== 'custom') {
        const selected = trucks.find(truck => String(truck.id) === truckSelect.value);
        if (!selected) throw new Error('Choose a truck.');
        if (selected.openings.length || trip) return selected;
        return {...selected, openings: [{side: 'rear', offset: 0, width: selected.width, height: selected.height, sill: 0}]};
    }
    const length = fieldNumber('customTruckLength', CM_PER_FOOT);
    const width = fieldNumber('customTruckWidth', CM_PER_FOOT);
    const height = fieldNumber('customTruckHeight', CM_PER_FOOT);
    const capacity = fieldNumber('customTruckCapacity');
    if (![length, width, height, capacity].every(value => Number.isFinite(value) && value > 0)
        || Math.max(length, width, height) > 2000) throw new Error('Enter the custom truck dimensions in feet and its payload in kg.');
    const a = get('customDoorWidth').value;
    const b = get('customDoorHeight').value;
    if (Boolean(a) !== Boolean(b)) throw new Error('Enter both door measurements, or leave both blank for a demo estimate.');
    const doorWidth = a ? Number(a) * CM_PER_FOOT : width;
    const doorHeight = b ? Number(b) * CM_PER_FOOT : height;
    if (!(doorWidth > 0 && doorWidth <= width && doorHeight > 0 && doorHeight <= height)) {
        throw new Error('The rear opening must be within the cargo dimensions.');
    }
    return {length, width, height, capacity, confirmed: false, openings: [
        {side: 'rear', offset: (width - doorWidth) / 2, width: doorWidth, height: doorHeight, sill: 0},
    ]};
}

function renderTruck(reset = false) {
    const isCustom = truckSelect.value === 'custom';
    get('customTruckFields').classList.toggle('d-none', !isCustom);
    const detail = get('truckMeasurements');
    const truck = trucks.find(value => String(value.id) === truckSelect.value);
    if (truck?.length && truck.width && truck.height) {
        detail.textContent = `${[truck.length, truck.width, truck.height].map(feet).join(' × ')} ft usable · ${number.format(truck.capacity)} kg · ${truck.openings.length} recorded openings.`;
        if (!truck.confirmed && truck.openings.length) detail.textContent += ' Existing opening is assumed to be at the rear. Confirm in Trucks before saving.';
        if (!truck.openings.length) detail.textContent += trip ? ' Add an opening in Trucks to plan this trip.' : ' This estimate assumes open rear access.';
        if (truck.demo) detail.textContent += ' Demo dimensions and payload.';
    } else {
        detail.textContent = isCustom ? 'One-time dimensions in feet.' : 'Select a truck with measured cargo space.';
    }
    if (reset) allowedDoors = {};
    const list = get('deliveryDoors');
    list.replaceChildren();
    const raw = truck?.openings.length ? truck.openings : (!trip && truckSelect.value ? [{side: 'rear'}] : []);
    for (const stop of deliveryStops) {
        const fieldset = el('fieldset', '', 'border rounded-3 p-2 mb-2');
        fieldset.append(el('legend', stopName(stop), 'float-none w-auto px-1 small fw-semibold mb-0'));
        raw.forEach((door, id) => {
            const label = el('label', '', 'door-toggle');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            const suggested = demo && raw.some(opening => opening.side === (stop.order === 1 ? 'right' : 'rear'));
            const fallback = suggested ? door.side === (stop.order === 1 ? 'right' : 'rear') : true;
            checkbox.checked = allowedDoors[stop.id]?.includes(id) ?? fallback;
            checkbox.dataset.doorId = id;
            checkbox.addEventListener('change', () => {
                allowedDoors[stop.id] = [...fieldset.querySelectorAll('input:checked')].map(input => Number(input.dataset.doorId));
                invalidate();
            });
            label.append(checkbox, document.createTextNode(doorLabel({...door, id})));
            fieldset.append(label);
        });
        if (!raw.length) fieldset.append(el('p', 'Select a truck with openings to choose access.', 'small mb-0 text-secondary'));
        allowedDoors[stop.id] = [...fieldset.querySelectorAll('input:checked')].map(input => Number(input.dataset.doorId));
        list.append(fieldset);
    }
}

function addRow(line = null) {
    if (rows.children.length >= 40) {
        say('This estimate supports up to 40 item lines.', 'warning');
        return;
    }
    const row = get('loadRowTemplate').content.firstElementChild.cloneNode(true);
    const id = line?.id ?? nextId;
    nextId = Math.max(nextId, id + 1);
    row.dataset.id = id;
    const select = row.querySelector('.load-box-select');
    const quantity = row.querySelector('.load-quantity');
    select.setAttribute('aria-label', 'Package type');
    quantity.setAttribute('aria-label', 'Quantity');
    const custom = row.querySelector('.load-custom-fields');
    const shape = row.querySelector('.custom-shape');
    quantity.value = line?.quantity ?? 1;
    if (line) {
        row.savedBox = line.box;
        select.add(new Option(line.box.name + ' (saved)', 'saved'));
        select.value = 'saved';
    } else {
        select.value = catalog.length ? '' : 'custom';
    }
    const stop = document.createElement('select');
    stop.className = 'form-select form-select-sm';
    stop.replaceChildren(...deliveryStops.map(value => new Option(stopName(value), value.id)));
    if (line) stop.value = line.stopId;
    const support = document.createElement('select');
    support.className = 'form-select form-select-sm';
    support.replaceChildren(new Option('Can stack and carry cargo above', 'stack'), new Option('Top only', 'top'),
        new Option('Base only, can carry cargo above', 'base'), new Option('Floor only, keep space above clear', 'floor'));
    support.value = line ? (line.floorOnly ? (line.canSupport ? 'base' : 'floor') : (line.canSupport ? 'stack' : 'top')) : 'stack';
    for (const [label, input] of [['Deliver to', stop], ['Stacking', support]]) {
        const field = el('label', label, 'form-label small w-100 mt-2 mb-0 fw-semibold');
        field.append(input);
        row.append(field);
    }
    row.stopInput = stop;
    row.supportInput = support;

    function updateFields() {
        const box = select.value === 'saved' ? row.savedBox : catalog.find(value => String(value.id) === select.value);
        const kind = select.value === 'custom' ? shape.value : box?.shape;
        const cylinder = kind === 'Cylinder';
        const floorOnly = cylinder || kind === 'Irregular';
        support.disabled = floorOnly;
        if (floorOnly) support.value = 'floor';
        custom.classList.toggle('d-none', select.value !== 'custom');
        row.querySelector('.custom-width-wrap').classList.toggle('d-none', cylinder);
        row.querySelector('.custom-length-label').textContent = cylinder ? 'Diameter (cm)' : 'Length (cm)';
        row.querySelector('.custom-tilt').disabled = cylinder;
        row.querySelector('.custom-shape-hint').textContent = kind === 'Irregular'
            ? 'Measure the smallest rectangular envelope around the item, including protrusions.' : '';
        row.querySelector('.load-box-details').textContent = box
            ? `${box.length} × ${box.width} × ${box.height} cm · ${number.format(box.weight)} kg each` : '';
    }
    row.updateFields = updateFields;
    select.addEventListener('change', updateFields);
    shape.addEventListener('change', updateFields);
    row.addEventListener('input', invalidate);
    row.addEventListener('change', invalidate);
    row.querySelector('.load-remove').addEventListener('click', () => { row.remove(); invalidate(); });
    rows.append(row);
    updateFields();
}

function readLine(row) {
    const boxValue = row.querySelector('.load-box-select').value;
    const id = Number(row.dataset.id);
    const quantity = Number(row.querySelector('.load-quantity').value);
    const stopId = Number(row.stopInput.value);
    const support = row.supportInput.value;
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > 10000) throw new Error('Enter 1–10,000 units per item line.');
    let box = boxValue === 'saved' ? row.savedBox : catalog.find(value => String(value.id) === boxValue);
    if (boxValue === 'custom') {
        const value = s => Number(row.querySelector(s).value);
        const shape = row.querySelector('.custom-shape').value;
        const length = value('.custom-length');
        box = {
            id: `custom-${id}`, name: row.querySelector('.custom-name').value.trim() || 'Custom item',
            shape, length, width: shape === 'Cylinder' ? length : value('.custom-width'),
            height: value('.custom-height'), weight: value('.custom-weight'),
            canTilt: shape !== 'Cylinder' && row.querySelector('.custom-tilt').checked,
        };
        if (![box.length, box.width, box.height, box.weight].every(value => Number.isFinite(value) && value > 0)
            || Math.max(box.length, box.width, box.height) > 2000 || box.weight > 9999999.999) {
            throw new Error('Enter the dimensions and weight for each custom item.');
        }
    }
    if (!box) throw new Error('Choose a package for each item line.');
    return {
        id, stopId, quantity, box, floorOnly: box.shape !== 'Box' || ['floor', 'base'].includes(support),
        canSupport: box.shape === 'Box' && ['stack', 'base'].includes(support),
    };
}

function colorAt(index) {
    if (index < fixedColors.length) return fixedColors[index];
    const hue = (index * 137.507764) % 360;
    const t = n => (n + hue / 30) % 12;
    const channel = n => Math.round(255 * (.53 - .27 * Math.max(-1, Math.min(t(n) - 3, 9 - t(n), 1))));
    return '#' + [0, 8, 4].map(n => channel(n).toString(16).padStart(2, '0')).join('');
}

function showView(threeD) {
    onlyFloor = !threeD;
    get('cargo3d').hidden = !threeD;
    get('cargoFloor').hidden = threeD;
    for (const [button, active] of [[get('view3d'), threeD], [get('viewFloor'), !threeD]]) {
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active);
    }
    if (threeD) scene?.redraw();
    if (current) updateLayout();
}

function updateLayout() {
    if (!current) return;
    const {result, packages, steps} = current;
    const colorByStop = get('cargoColor').value === 'stop';
    const keys = colorByStop ? deliveryStops.map(stop => stop.id) : [...packages.keys()];
    const colors = new Map(keys.map((key, index) => [key, colorAt(index)]));
    const colorKey = row => colorByStop ? row.stopId : row.boxId;
    const onlyStop = get('cargoStop').value;
    const level = get('cargoLevel').value === '' || onlyFloor ? Infinity : Number(get('cargoLevel').value) + .00001;
    displayed = result.placements.filter(row => row.z <= level && (!onlyStop || String(row.stopId) === onlyStop));
    const placement = get('cargoPlacement');
    const previous = placement.selectedOptions[0]?.textContent;
    placement.replaceChildren(new Option('Choose a placement', ''));
    for (const [index, row] of displayed.entries()) {
        const step = steps.get(row);
        placement.add(new Option(`${step}. ${packages.get(row.boxId).name} · ${stopById.get(row.stopId).name}`, index));
    }
    const restored = [...placement.options].find(option => option.textContent === previous);
    if (restored) placement.value = restored.value;
    get('cargoInspector').hidden = !displayed.length;
    const legend = get('floorLegend');
    legend.replaceChildren();
    for (const key of new Set(displayed.map(colorKey))) {
        const box = packages.get(key);
        const label = colorByStop ? stopById.get(key).name : box.name + (box.shape === 'Irregular' ? ' (reserved space)' : '');
        const swatch = el('span', '', 'load-swatch');
        swatch.style.backgroundColor = colors.get(key);
        const item = el('span', '', 'me-3 d-inline-block');
        item.append(swatch, document.createTextNode(label));
        legend.append(item);
    }
    current.view = {colors, colorKey};
    renderLoadFloor(get('loadFloor'), {...result, placements: displayed}, colors, colorKey);
    updateSelection();
}

function updateSelection() {
    if (!current?.view) return;
    const {result, packages, view, steps} = current;
    const key = get('cargoPlacement').value;
    const row = key !== '' ? displayed[Number(key)] : null;
    if (!onlyFloor && scene) scene.setLoad(result, displayed, view.colors, packages, row, view.colorKey);
    const door = row && result.openings[row.doorId];
    get('cargoPlacementDetail').textContent = row
        ? `Load step ${steps.get(row)} · ${doorLabel(door)}. From cab ${feet(row.x)} ft, left wall ${feet(row.y)} ft, floor ${feet(row.z)} ft. Orientation ${[row.l, row.w, row.h].map(number.format).join(' × ')} cm along truck length, width and height.`
        : '';
}

function groupedSteps(items, result, packages, reverse = false) {
    const list = el('ol', '', 'crew-steps ps-4 mb-0');
    let prior;
    for (const row of items) {
        const id = `${row.stopId}:${row.boxId}:${row.doorId}`;
        if (prior?.id !== id) {
            prior = {id, count: 0, li: document.createElement('li')};
            list.append(prior.li);
        }
        prior.count++;
        const action = reverse ? 'Unload' : 'Load';
        prior.li.textContent = `${action} ${prior.count} × ${packages.get(row.boxId).name} via ${doorLabel(result.openings[row.doorId])} · ${stopById.get(row.stopId).name}`;
    }
    return list;
}

function renderInstructions(result, packages, lines, loadSteps) {
    const summary = get('plannedResult');
    const crew = get('crewInstructions');
    summary.replaceChildren();
    crew.replaceChildren();
    const warehouse = document.createElement('details');
    warehouse.open = true;
    warehouse.append(el('summary', 'At the warehouse', 'fw-semibold py-2'), groupedSteps(loadSteps, result, packages));
    if (result.placed) crew.append(warehouse);
    else crew.append(el('p', 'No units could be placed in this estimate.'));
    for (const stop of deliveryStops) {
        const atStop = lines.filter(line => line.stopId === stop.id);
        const delivered = result.placements.filter(row => row.stopId === stop.id).reverse();
        if (!atStop.length) continue;
        const card = el('section', '', 'border-top py-3');
        card.append(el('h3', stopName(stop), 'h6 fw-semibold'));
        for (const line of atStop) {
            const row = result.results[line.id];
            card.append(el('p', `${line.box.name} · ${row.loaded} loaded of ${line.quantity} · ${line.quantity - row.loaded} left`, 'small mb-1'));
            if (row.reason) card.append(el('p', row.reason, 'small text-warning-emphasis mb-2'));
        }
        summary.append(card);
        const details = document.createElement('details');
        details.className = 'border-top';
        details.append(el('summary', `${stopName(stop)} · ${delivered.length} to unload`, 'py-2 fw-semibold'));
        if (delivered.length) details.append(groupedSteps(delivered, result, packages, true));
        crew.append(details);
    }
}

function showResult(inputs, result, saved = false) {
    const packages = new Map(inputs.lines.map(line => [line.box.id, line.box]));
    const loadSteps = loadingOrder(result, result.placements, inputs.stops).map(index => result.placements[index]);
    const steps = new Map(loadSteps.map((row, i) => [row, i + 1]));
    current = {inputs, result, packages, steps};
    const requested = inputs.lines.reduce((sum, line) => sum + line.quantity, 0);
    const percent = Math.min(100, 100 * result.envelopeVolume / result.volume);
    get('placedMetric').textContent = number.format(result.placed);
    get('placedSub').textContent = `of ${number.format(requested)} requested`;
    get('weightMetric').textContent = `${number.format(result.weight)} kg`;
    get('weightSub').textContent = `${number.format(Math.max(0, result.capacity - result.weight))} kg payload remaining`;
    get('spacePercent').textContent = `${number.format(percent)}%`;
    get('spaceBar').style.width = `${percent}%`;
    get('spaceText').textContent = `${number.format(result.envelopeVolume / 1e6)} m³ occupied of ${number.format(result.volume / 1e6)} m³ usable.`;
    const level = get('cargoLevel');
    const levels = [...new Set(result.placements.map(row => Math.round(row.z * 1e6) / 1e6))].sort((a, b) => a - b);
    level.replaceChildren(new Option('All levels', ''), ...levels.map(z => new Option(z === 0 ? 'Floor only' : `Through ${feet(z)} ft`, z)));
    get('cargoFilters').hidden = false;
    get('cargoControls').hidden = false;
    get('cargoPlaceholder').hidden = true;
    if (scene === undefined) {
        try { scene = createLoadView(get('loadCanvas'), get('cargoCab'), get('cargoRear')); }
        catch { scene = null; }
        if (!scene) {
            get('view3d').disabled = true;
            get('loadFloor').before(el('p', '3D is unavailable on this device. The floor view and crew instructions are available.', 'small text-secondary'));
            showView(false);
        }
    }
    const capacityBoxes = new Map([...catalog, ...packages.values()].map(box => [box.id, box]));
    get('capacityPackage').replaceChildren(new Option('Choose a package', ''), ...[...capacityBoxes.values()].map(box => new Option(box.name, box.id)));
    renderInstructions(result, packages, inputs.lines, loadSteps);
    updateLayout();
    updateActions();
    const missing = requested - result.placed;
    const message = missing ? `${number.format(missing)} units could not be placed with the selected openings and stacking rules.`
        : 'All requested units fit with clear unloading paths in the estimate.';
    say((saved ? 'Saved load plan. ' : '') + message, missing ? 'warning' : 'success');
}

async function calculate(scroll = true) {
    invalidate();
    get('calculateLoad').disabled = true;
    try {
        const truck = readTruck();
        const clearance = fieldNumber('wallClearance');
        if (trip && !truck.openings.length) throw new Error('Add the truck’s openings before estimating a trip.');
        const stops = deliveryStops.map(stop => ({...stop, doors: allowedDoors[stop.id] ?? []}));
        const lines = rowNodes().map(readLine);
        if (!lines.length) throw new Error('Add at least one item to estimate the load.');
        const inputs = {truck, clearance, stops, lines};
        say('Checking dimensions, doors and delivery order…');
        const result = await runWorker({type: 'plan', inputs});
        showResult(inputs, result);
        if (scroll && window.matchMedia('(max-width: 991px)').matches) get('estimatePanel').scrollIntoView({block: 'start'});
    } catch (error) {
        if (error.name !== 'AbortError') say(error.message, 'warning');
    } finally {
        if (!worker) get('calculateLoad').disabled = false;
    }
}

function restore(data) {
    for (const line of data.lines) addRow(line);
    get('wallClearance').value = data.clearance;
    if (!trip.saved.current) {
        say('The truck or delivery stops changed. Estimate again before using the saved layout.', 'warning');
        return;
    }
    const truck = readTruck();
    const stops = deliveryStops.map(stop => ({...stop, doors: allowedDoors[stop.id] ?? []}));
    const bay = cargoBay(truck, data.clearance);
    const results = Object.fromEntries(data.lines.map(line => [line.id, {loaded: 0, reason: ''}]));
    let envelopeVolume = 0;
    let weight = 0;
    const lines = new Map(data.lines.map(line => [line.id, line]));
    for (const row of data.placements) {
        results[row.lineId].loaded++;
        weight += lines.get(row.lineId).box.weight;
        envelopeVolume += row.l * row.w * row.h;
    }
    for (const line of data.lines) {
        if (results[line.id].loaded < line.quantity) results[line.id].reason = data.results?.[line.id] ?? 'Partial load saved';
    }
    showResult({truck, stops, lines: data.lines, clearance: data.clearance},
        {...bay, results, placements: data.placements, placed: data.placements.length, weight, envelopeVolume}, true);
}

get('capacityForm').addEventListener('submit', async event => {
    event.preventDefault();
    if (!current) return;
    const {inputs, result, packages} = current;
    const id = get('capacityPackage').value;
    const stopId = Number(get('capacityStop').value);
    const box = [...packages.values(), ...catalog].find(value => String(value.id) === id);
    if (!box) return;
    const line = inputs.lines.find(value => value.stopId === stopId && value.box.id === box.id);
    const example = line ?? {id: nextId, stopId, box, canSupport: box.shape === 'Box', floorOnly: box.shape !== 'Box'};
    const output = get('extraResult');
    capacityCheck.disabled = true;
    output.textContent = 'Checking capacity…';
    try {
        const value = await runWorker({type: 'capacity', inputs, result, example});
        const plus = value.capped ? ' (estimate reached its calculation limit)' : '';
        output.textContent = `Empty truck: ${number.format(value.empty)} units. With this load: ${value.extra === null ? 'no additional fit found while keeping the current quantities' : number.format(value.extra) + ' extra units'}.${plus}`;
    } catch (error) {
        if (error.name !== 'AbortError') output.textContent = error.message;
    } finally {
        updateActions();
    }
});

saveButton?.addEventListener('click', async () => {
    if (!current || saving) return;
    const {inputs, result} = current;
    saving = true;
    updateActions();
    try {
        const response = await fetch(root.dataset.endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': root.dataset.csrf},
            body: JSON.stringify({
                revision, sourceHash: trip.hash, tripId: trip.id,
                clearance: inputs.clearance, lines: inputs.lines, placements: result.placements,
                stopDoors: Object.fromEntries(inputs.stops.map(stop => [stop.id, stop.doors])),
                results: Object.fromEntries(Object.entries(result.results).map(([id, value]) => [id, value.reason])),
            }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not save the load plan.');
        revision = data.revision;
        say(`Load plan saved to ${trip.number}.`, 'success');
    } catch (error) {
        say(error.message, 'warning');
    } finally {
        saving = false;
        updateActions();
    }
});

get('addLoadRow').addEventListener('click', () => { addRow(); invalidate(); });
get('calculateLoad').addEventListener('click', () => calculate());
get('wallClearance').addEventListener('input', invalidate);
for (const input of root.querySelectorAll('.custom-truck-field')) input.addEventListener('input', invalidate);
truckSelect.addEventListener('change', () => { renderTruck(true); invalidate(); });
get('view3d').addEventListener('click', () => showView(true));
get('viewFloor').addEventListener('click', () => showView(false));
for (const id of ['cargoStop', 'cargoColor', 'cargoLevel']) get(id).addEventListener('change', updateLayout);
get('cargoPlacement').addEventListener('change', updateSelection);

const yaw = get('cargoTurn');
const elevation = get('cargoTilt');
const rotate = () => scene?.rotate(Number(yaw.value), Number(elevation.value));
yaw.addEventListener('input', rotate);
elevation.addEventListener('input', rotate);
get('cargoReset').addEventListener('click', () => {
    yaw.value = yaw.defaultValue;
    elevation.value = elevation.defaultValue;
    rotate();
});
let drag;
get('loadCanvas').addEventListener('pointerdown', event => {
    if (!scene) return;
    drag = {id: event.pointerId, x: event.clientX, yaw: Number(yaw.value)};
    event.currentTarget.setPointerCapture(event.pointerId);
});
get('loadCanvas').addEventListener('pointermove', event => {
    if (drag?.id !== event.pointerId) return;
    yaw.value = ((drag.yaw + event.clientX - drag.x + 540) % 360) - 180;
    rotate();
});
for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) get('loadCanvas').addEventListener(type, () => { drag = null; });

for (const stop of deliveryStops) {
    get('cargoStop').add(new Option(stopName(stop), stop.id));
    get('capacityStop').add(new Option(stopName(stop), stop.id));
}
if (trip?.saved?.current) allowedDoors = trip.saved.payload.stopDoors;
if (demo) {
    const van = trucks.find(truck => truck.plate === 'DEMO-CV16');
    if (van) truckSelect.value = van.id;
}
renderTruck();
if (trip?.saved) {
    restore(trip.saved.payload);
} else if (demo) {
    const sample = [['DEMO-SMALL', 1, 16], ['DEMO-WRAP', 1, 2], ['DEMO-CARTON', 2, 24], ['DEMO-TALL', 2, 4]];
    for (const [code, stopId, quantity] of sample) {
        const box = catalog.find(item => item.code === code);
        if (box) addRow({id: nextId, box, stopId, quantity, floorOnly: box.shape !== 'Box', canSupport: box.shape === 'Box' && code !== 'DEMO-TALL'});
    }
    if (rows.children.length) void calculate(false);
} else {
    addRow();
}
