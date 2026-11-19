import assert from 'node:assert/strict';
import {clearPath, fitsOpening, loadingOrder, packageCapacity, planLoad} from '../public/assets/js/stop-packing.mjs';

const rear = {side: 'rear', offset: 0, width: 4, sill: 0, height: 4};
const right = {side: 'right', offset: 1, width: 3, sill: 0, height: 4};
const truck = {length: 6, width: 4, height: 4, capacity: 100, openings: [rear, right]};
const stops = [{id: 11, doors: [1]}, {id: 12, doors: [0]}];
const carton = {id: 'carton', name: 'Carton', length: 2, width: 2, height: 1, weight: 1, shape: 'Box'};
const line = (id, stopId, quantity, box = carton, rules = {}) => ({
    id, stopId, quantity, box, floorOnly: false, canSupport: true, ...rules,
});
const lines = [line(1, 11, 3), line(2, 12, 3)];

function verifyPaths(plan) {
    const loadOrder = loadingOrder(plan, plan.placements, stops);
    assert.equal(new Set(loadOrder).size, plan.placed);
    const onBoard = [];
    for (const index of loadOrder) {
        const item = plan.placements[index];
        const door = plan.openings[item.doorId];
        assert.ok(fitsOpening(item, door));
        assert.ok(clearPath(item, door, plan, onBoard));
        onBoard.push(item);
    }
    const unloading = [...plan.placements].reverse();
    let lastStop = 0;
    for (const item of unloading) {
        assert.ok(item.stopId >= lastStop);
        lastStop = item.stopId;
        onBoard.splice(onBoard.indexOf(item), 1);
        assert.ok(clearPath(item, plan.openings[item.doorId], plan, onBoard));
    }
}

const mixed = planLoad(truck, 0, lines, stops);
assert.equal(mixed.placed, 6);
assert.ok(mixed.placements.filter(item => item.stopId === 11).every(item => item.doorId === 1));
verifyPaths(mixed);
const independent = planLoad({...truck, openings: [{...rear, width: 2}, right]}, 0, [
    line(1, 11, 1),
    line(2, 12, 1, {...carton, length: 6}),
], stops);
assert.equal(independent.placed, 2);
verifyPaths(independent);
assert.equal(independent.placements[loadingOrder(independent, independent.placements, stops)[0]].stopId, 11);

const singleDoor = planLoad({...truck, openings: [rear]}, 0, lines, stops.map(stop => ({...stop, doors: [0]})));
assert.equal(singleDoor.placed, 6);
assert.equal(singleDoor.placements[loadingOrder(singleDoor, singleDoor.placements, stops)[0]].stopId, 12);
verifyPaths(singleDoor);
assert.ok(new Set(singleDoor.placements.filter(item => item.z === 0).map(item => item.y)).size > 1);

const restricted = planLoad({...truck, openings: [rear, {...right, width: 1}]}, 0, lines, stops);
assert.equal(restricted.results[1].loaded, 0);
assert.match(restricted.results[1].reason, /opening/);
assert.equal(restricted.results[2].loaded, 3);
const raised = planLoad({...truck, openings: [rear, {...right, sill: 1}]}, 0,
    [line(1, 11, 1, carton, {floorOnly: true})], stops);
assert.equal(raised.placed, 0);

const fragile = {...carton, id: 'wrapped', shape: 'Irregular'};
const singleFootprint = {...truck, length: 2, width: 2, openings: [{...rear, width: 2}]};
const stop = [{id: 11, doors: [0]}];
const wrappedLoad = planLoad(singleFootprint, 0, [line(1, 11, 8, fragile, {floorOnly: true, canSupport: false})], stop);
assert.equal(wrappedLoad.placed, 1);
const estimate = packageCapacity(truck, 0, lines, stops, mixed, lines[0]);
assert.ok(estimate.empty >= mixed.results[1].loaded);
assert.ok(estimate.extra === null || estimate.extra >= 0);
console.log('Stop order, side door access, load order, floor spread, wrapped items and capacity passed.');
