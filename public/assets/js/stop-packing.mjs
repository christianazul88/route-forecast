import {orientations} from './load-packing.mjs?v=load2';

const EPS = .00001;
export const MAX_PLACEMENTS = 1200;
const overlap = (a, al, b, bl) => a < b + bl - EPS && b < a + al - EPS;
const intersects = (a, b) => overlap(a.x, a.l, b.x, b.l)
    && overlap(a.y, a.w, b.y, b.w) && overlap(a.z, a.h, b.z, b.h);
const volume = box => box.length * box.width * box.height;

export function cargoBay(truck, clearance) {
    if (![truck?.length, truck?.width, truck?.height, truck?.capacity].every(value => Number.isFinite(value) && value > 0)) {
        throw new Error('Enter the usable length, width, height and payload for this truck.');
    }
    if (!Number.isFinite(clearance) || clearance < 0 || clearance > 50) {
        throw new Error('Clearance must be between 0 and 50 cm.');
    }
    const length = truck.length - 2 * clearance;
    const width = truck.width - 2 * clearance;
    const height = truck.height - clearance;
    if (Math.min(length, width, height) <= 0) throw new Error('Clearance leaves no usable cargo space.');
    const openings = (truck.openings ?? []).map((door, id) => ({
        ...door, id,
        start: Math.max(0, door.offset),
        end: Math.min(door.side === 'rear' ? width : length, door.offset + door.width - 2 * clearance),
        top: Math.min(height, door.sill + door.height - clearance),
    }));
    return {length, width, height, volume: length * width * height, capacity: truck.capacity, openings};
}

export function doorLabel(door) {
    return `${{rear: 'Rear', left: 'Left side', right: 'Right side'}[door.side]} · opening ${door.id + 1}`;
}

export function fitsOpening(box, door) {
    const rear = door.side === 'rear';
    const start = rear ? box.y : box.x;
    return start >= door.start - EPS && start + (rear ? box.w : box.l) <= door.end + EPS
        && box.z >= door.sill - EPS && box.z + box.h <= door.top + EPS;
}

export function doorPath(box, door, bay) {
    if (door.side === 'rear') return {...box, x: box.x + box.l, l: bay.length - box.x - box.l};
    if (door.side === 'left') return {...box, y: 0, w: box.y};
    return {...box, y: box.y + box.w, w: bay.width - box.y - box.w};
}

export function clearPath(box, door, bay, cargo) {
    const path = doorPath(box, door, bay);
    if (Math.min(path.l, path.w, path.h) <= EPS) return true;
    return !cargo.some(other => intersects(path, other));
}

function supported(box, cargo) {
    if (box.z < EPS) return true;
    let area = 0;
    for (const other of cargo) {
        if (!other.canSupport || Math.abs(box.z - (other.z + other.h)) > EPS) continue;
        const width = Math.min(other.y + other.w, box.y + box.w) - Math.max(other.y, box.y);
        const length = Math.min(other.x + other.l, box.x + box.l) - Math.max(other.x, box.x);
        if (width > EPS && length > EPS) area += width * length;
    }
    return area >= box.l * box.w - EPS;
}

function splitSpace(spaces, index, box, lengthFirst) {
    const s = spaces[index];
    const remaining = [];
    const add = (x, y, z, l, w, h) => {
        if (Math.min(l, w, h) > EPS) remaining.push({x, y, z, l, w, h});
    };
    if (lengthFirst) {
        add(s.x, s.y, s.z, box.x - s.x, s.w, s.h);
        add(box.x + box.l, s.y, s.z, s.x + s.l - box.x - box.l, s.w, s.h);
        add(box.x, s.y, s.z, box.l, box.y - s.y, s.h);
        add(box.x, box.y + box.w, s.z, box.l, s.y + s.w - box.y - box.w, s.h);
    } else {
        add(s.x, s.y, s.z, s.l, box.y - s.y, s.h);
        add(s.x, box.y + box.w, s.z, s.l, s.y + s.w - box.y - box.w, s.h);
        add(s.x, box.y, s.z, box.x - s.x, box.w, s.h);
        add(box.x + box.l, box.y, s.z, s.x + s.l - box.x - box.l, box.w, s.h);
    }
    if (box.canSupport) {
        add(box.x, box.y, box.z + box.h, box.l, box.w, s.z + s.h - box.z - box.h);
    }
    spaces.splice(index, 1, ...remaining);
}

function positionCandidates(space, size, door) {
    const rear = door.side === 'rear';
    const limitX = space.x + space.l - size.l;
    const limitY = space.y + space.w - size.w;
    const z = Math.max(space.z, door.sill);
    if (limitX < space.x - EPS || limitY < space.y - EPS || z + size.h > space.z + space.h + EPS
        || z + size.h > door.top + EPS) return [];
    const a = rear ? space.y : space.x;
    const b = rear ? limitY : limitX;
    const lower = Math.max(a, door.start);
    const upper = Math.min(b, door.end - (rear ? size.w : size.l));
    if (lower > upper + EPS) return [];
    const across = Math.abs(upper - lower) < EPS ? [lower] : [lower, upper];
    return across.map(value => ({
        ...size, z,
        x: rear ? space.x : value,
        y: rear ? value : (door.side === 'left' ? limitY : space.y),
    }));
}

function canUse(door, size) {
    return (door.side === 'rear' ? size.w : size.l) <= door.end - door.start + EPS
        && size.h <= door.top - door.sill + EPS;
}

function gapFromDoor(box, door, bay) {
    const path = doorPath(box, door, bay);
    return door.side === 'rear' ? path.l / bay.length : path.w / bay.width;
}

function choosePlacement(state, line, sizes, doors, strategy) {
    let best = null;
    for (const [index, space] of state.spaces.entries()) {
        if (line.floorOnly && space.z > EPS) continue;
        for (const door of doors) {
            for (const size of sizes) {
                if (!canUse(door, size)) continue;
                for (const box of positionCandidates(space, size, door)) {
                    if (line.floorOnly && box.z > EPS) continue;
                    const depth = gapFromDoor(box, door, state.bay);
                    const preference = strategy === 'compact' ? -depth * 1.8 + box.z / state.bay.height
                        : box.z * 2 / state.bay.height - depth;
                    if (best && preference >= best.preference - EPS) continue;
                    if (!supported(box, state.items) || !clearPath(box, door, state.bay, state.items)) continue;
                    best = {box, door, index, preference};
                }
            }
        }
    }
    return best;
}

function placeLine(state, line, allowed, strategy, budget) {
    const sizes = orientations(line.box, {doorWidth: null, doorHeight: null});
    const doors = allowed.filter(door => sizes.some(size => canUse(door, size)));
    if (!allowed.length) return {loaded: 0, reason: 'No opening available at this stop'};
    if (!doors.length) return {loaded: 0, reason: 'Package does not fit an available opening'};
    let loaded = 0;
    let reason = '';
    const limit = Math.min(line.quantity, budget);
    while (loaded < limit) {
        if (state.weight + line.box.weight > state.bay.capacity + EPS) {
            reason = 'Payload limit';
            break;
        }
        const found = choosePlacement(state, line, sizes, doors, strategy);
        if (!found) {
            const fitsInside = sizes.some(({l,w,h}) => l <= state.bay.length && w <= state.bay.width && h <= state.bay.height);
            reason = fitsInside ? 'Free space, access path or stack support' : 'Package exceeds cargo dimensions';
            break;
        }
        const box = {
            ...found.box, boxId: line.box.id, stopId: line.stopId, lineId: line.id,
            doorId: found.door.id, count: 1, canSupport: line.canSupport,
        };
        splitSpace(state.spaces, found.index, box, strategy !== 'width');
        state.items.push(box);
        state.weight += line.box.weight;
        state.envelopeVolume += volume(line.box);
        loaded++;
    }
    if (!reason && loaded < line.quantity) reason = 'Planner limit reached';
    return {loaded, reason};
}

function simulate(bay, lines, stops, strategy) {
    const state = {
        bay, weight: 0, envelopeVolume: 0, items: [],
        spaces: [{x: 0, y: 0, z: 0, l: bay.length, w: bay.width, h: bay.height}],
    };
    const results = {};
    const stopTotals = [];
    for (const stop of [...stops].reverse()) {
        const doors = bay.openings.filter(door => stop.doors.includes(door.id));
        const goods = lines.filter(line => line.stopId === stop.id);
        goods.sort((a, b) => Number(b.floorOnly) - Number(a.floorOnly)
            || (strategy === 'width' ? volume(a.box) - volume(b.box) : volume(b.box) - volume(a.box)));
        let total = 0;
        for (const line of goods) {
            const result = placeLine(state, line, doors, strategy, MAX_PLACEMENTS - state.items.length);
            results[line.id] = result;
            total += result.loaded;
        }
        stopTotals.unshift(total);
    }
    return {
        ...bay, placed: state.items.length, weight: state.weight, envelopeVolume: state.envelopeVolume,
        placements: state.items, results, stopTotals,
    };
}

function better(candidate, best) {
    if (candidate.placed !== best.placed) return candidate.placed > best.placed;
    for (const [index, total] of candidate.stopTotals.entries()) {
        if (total !== best.stopTotals[index]) return total > best.stopTotals[index];
    }
    return false;
}

export function planLoad(truck, clearance, lines, stops) {
    const bay = cargoBay(truck, clearance);
    let best = simulate(bay, lines, stops, 'floor');
    const request = lines.reduce((sum, line) => sum + line.quantity, 0);
    if (best.placed === request) return best;
    for (const strategy of ['width', 'compact']) {
        const candidate = simulate(bay, lines, stops, strategy);
        if (better(candidate, best)) best = candidate;
        if (best.placed === request) break;
    }
    return best;
}

export function loadingOrder(bay, cargo, stops) {
    const edges = cargo.map(() => []);
    const dependencies = cargo.map(() => 0);
    const paths = cargo.map(box => doorPath(box, bay.openings[box.doorId], bay));
    for (let i = 0; i < cargo.length; i++) {
        const box = cargo[i];
        const path = paths[i];
        for (let j = i + 1; j < cargo.length; j++) {
            const next = cargo[j];
            const supports = Math.abs(box.z + box.h - next.z) < EPS
                && overlap(box.x, box.l, next.x, next.l) && overlap(box.y, box.w, next.y, next.w);
            const blocks = Math.min(path.l, path.w, path.h) > EPS && intersects(path, next);
            if (supports || blocks) {
                edges[i].push(j);
                dependencies[j]++;
            }
        }
    }
    const stopOrder = new Map(stops.map((stop, i) => [stop.id, i]));
    const waiting = cargo.map((_, i) => i).filter(i => !dependencies[i]);
    const ordered = [];
    let lastDoor = null;
    while (waiting.length) {
        const firstStop = new Map();
        for (const candidate of waiting) {
            const {doorId, stopId} = cargo[candidate];
            firstStop.set(doorId, Math.min(firstStop.get(doorId) ?? Infinity, stopOrder.get(stopId)));
        }
        waiting.sort((a, b) => {
            const A = cargo[a];
            const B = cargo[b];
            return Number(B.doorId === lastDoor) - Number(A.doorId === lastDoor)
                || firstStop.get(A.doorId) - firstStop.get(B.doorId) || A.doorId - B.doorId || a - b;
        });
        const i = waiting.shift();
        ordered.push(i);
        lastDoor = cargo[i].doorId;
        for (const other of edges[i]) if (--dependencies[other] === 0) waiting.push(other);
    }
    return ordered;
}

export function packageCapacity(truck, clearance, lines, stops, current, example) {
    const limit = MAX_PLACEMENTS;
    const single = planLoad(truck, clearance, [{...example, quantity: limit}], stops);
    const base = current.results[example.id]?.loaded ?? 0;
    const expanded = lines.map(line => ({
        ...line, quantity: line.id === example.id ? base + limit
            : current.results[line.id]?.loaded ?? 0,
    }));
    if (!expanded.some(line => line.id === example.id)) expanded.push({...example, quantity: limit});
    const mixed = planLoad(truck, clearance, expanded, stops);
    const keptManifest = lines.every(line => (mixed.results[line.id]?.loaded ?? 0) >= (current.results[line.id]?.loaded ?? 0));
    return {
        empty: single.placed,
        extra: keptManifest ? (mixed.results[example.id]?.loaded ?? 0) - base : null,
        capped: single.placed === limit || (keptManifest && mixed.placed === limit),
    };
}
