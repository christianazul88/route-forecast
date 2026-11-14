// Conservative, axis-aligned guillotine packing estimate. Dimensions are centimeters.
const EPS = 1e-7;

function finitePositive(value) {
    return Number.isFinite(Number(value)) && Number(value) > 0;
}

export function createLoadState(truck, clearance = 2) {
    if (![truck.length, truck.width, truck.height, truck.capacity].every(finitePositive)) {
        throw new Error('This truck needs measured interior dimensions and a payload capacity.');
    }
    if (!Number.isFinite(clearance) || clearance < 0 || clearance > 50) {
        throw new Error('Clearance must be between 0 and 50 cm.');
    }
    const length = truck.length - 2 * clearance;
    const width = truck.width - 2 * clearance;
    const height = truck.height - clearance;
    if (Math.min(length, width, height) <= 0) {
        throw new Error('Clearance leaves no usable cargo space.');
    }
    const doorWidth = truck.doorWidth == null ? null : truck.doorWidth - 2 * clearance;
    const doorHeight = truck.doorHeight == null ? null : truck.doorHeight - clearance;
    return {
        length, width, height,
        doorWidth, doorHeight,
        capacity: truck.capacity,
        weight: 0,
        envelopeVolume: 0,
        volume: length * width * height,
        placed: 0,
        spaces: [{x: 0, y: 0, z: 0, l: length, w: width, h: height}],
        floorRows: [],
    };
}

export function cloneLoadState(state) {
    return {
        ...state,
        spaces: state.spaces.map(space => ({...space})),
        floorRows: state.floorRows.map(row => ({...row})),
    };
}

export function orientations(box, state) {
    const l = Number(box.length);
    const w = Number(box.width);
    const h = Number(box.height);
    if (![l, w, h, Number(box.weight)].every(finitePositive)) return [];
    const candidates = box.shape === 'Cylinder'
        ? [[l, l, h]]
        : box.canTilt
            ? [[l, w, h], [w, l, h], [l, h, w], [h, l, w], [w, h, l], [h, w, l]]
            : [[l, w, h], [w, l, h]];
    const seen = new Set();
    return candidates.filter(([a, b, c]) => {
        const key = `${a}/${b}/${c}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return (state.doorWidth === null || b <= state.doorWidth + EPS)
            && (state.doorHeight === null || c <= state.doorHeight + EPS);
    }).map(([a, b, c]) => ({l: a, w: b, h: c}));
}

function addSpace(spaces, space) {
    if (space.l > EPS && space.w > EPS && space.h > EPS) spaces.push(space);
}

export function packInto(state, box, requested, maxRows = 4000) {
    if (!Number.isSafeInteger(requested) || requested < 0 || requested > 10000) {
        throw new Error('Quantity must be a whole number from 0 to 10,000.');
    }
    const options = orientations(box, state);
    if (options.length === 0) return {placed: 0, reason: 'Cannot pass through the door'};
    let placed = 0;
    let rows = 0;
    while (placed < requested && rows < maxRows) {
        const byWeight = Math.floor((state.capacity - state.weight + EPS) / box.weight);
        if (byWeight <= 0) break;
        let best = null;
        for (let i = 0; i < state.spaces.length; i++) {
            const space = state.spaces[i];
            for (const orientation of options) {
                const nx = Math.floor((space.l + EPS) / orientation.l);
                const ny = Math.floor((space.w + EPS) / orientation.w);
                const nz = Math.floor((space.h + EPS) / orientation.h);
                if (nx < 1 || ny < 1 || nz < 1) continue;
                const potential = Math.min(requested - placed, byWeight, nx * ny * nz);
                if (potential <= 0) continue;
                const score = potential;
                const waste = space.l * space.w * space.h - potential * orientation.l * orientation.w * orientation.h;
                if (best === null || score > best.score || (score === best.score && waste < best.waste)) {
                    best = {index: i, orientation, nx, score, waste};
                }
            }
        }
        if (best === null) break;
        const space = state.spaces.splice(best.index, 1)[0];
        const count = Math.min(requested - placed, byWeight, best.nx);
        const usedL = count * best.orientation.l;
        const usedW = best.orientation.w;
        const usedH = best.orientation.h;
        addSpace(state.spaces, {x: space.x + usedL, y: space.y, z: space.z, l: space.l - usedL, w: space.w, h: space.h});
        addSpace(state.spaces, {x: space.x, y: space.y + usedW, z: space.z, l: usedL, w: space.w - usedW, h: space.h});
        addSpace(state.spaces, {x: space.x, y: space.y, z: space.z + usedH, l: usedL, w: usedW, h: space.h - usedH});
        if (Math.abs(space.z) < EPS && state.floorRows.length < 150) {
            state.floorRows.push({x: space.x, y: space.y, l: usedL, w: usedW, boxId: box.id, count});
        }
        placed += count;
        state.placed += count;
        state.weight += count * box.weight;
        state.envelopeVolume += count * best.orientation.l * best.orientation.w * best.orientation.h;
        rows++;
    }
    let reason = '';
    if (placed < requested) {
        reason = rows >= maxRows ? 'Estimate reached planning limit'
            : state.capacity - state.weight + EPS < box.weight ? 'Payload limit' : 'Available space or shape';
    }
    return {placed, reason};
}
