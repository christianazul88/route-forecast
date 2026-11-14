import assert from 'node:assert/strict';
import {createLoadState, cloneLoadState, packInto} from '../public/assets/js/load-packing.mjs';

const box = (overrides = {}) => ({id: 1, shape: 'Box', length: 1, width: 1, height: 1, weight: 1, canTilt: false, ...overrides});
const truck = (overrides = {}) => ({length: 2, width: 2, height: 2, capacity: 100, doorWidth: null, doorHeight: null, ...overrides});

{
    const state = createLoadState(truck(), 0);
    assert.equal(packInto(state, box(), 10).placed, 8);
    assert.equal(state.envelopeVolume, state.volume);
    assert.equal(state.weight, 8);
}
{
    const state = createLoadState(truck({capacity: 5}), 0);
    assert.deepEqual(packInto(state, box({weight: 2}), 5), {placed: 2, reason: 'Payload limit'});
    assert.equal(state.weight, 4);
}
{
    const state = createLoadState(truck({length: 2, width: 2, height: 1}), 0);
    assert.equal(packInto(state, box({length: 1, width: 1, height: 2}), 1).placed, 0);
    assert.equal(packInto(state, box({length: 1, width: 1, height: 2, canTilt: true}), 1).placed, 1);
}
{
    const state = createLoadState(truck({doorWidth: 0.75, doorHeight: 2}), 0);
    assert.deepEqual(packInto(state, box(), 1), {placed: 0, reason: 'Cannot pass through the door'});
}
{
    const state = createLoadState(truck({length: 3, width: 2, height: 1}), 0);
    assert.equal(packInto(state, box({id: 1, length: 2, width: 1, height: 1}), 1).placed, 1);
    assert.equal(packInto(state, box({id: 2}), 4).placed, 4);
    const copy = cloneLoadState(state);
    assert.equal(packInto(copy, box(), 10).placed, 0);
    assert.equal(state.placed, 5);
}
{
    const state = createLoadState(truck({length: 4, width: 4, height: 2}), 0.5);
    assert.equal(state.length, 3);
    assert.equal(state.width, 3);
    assert.equal(state.height, 1.5);
    assert.equal(packInto(state, box({shape: 'Cylinder', length: 1, width: 1}), 20).placed, 9);
}

console.log('Load packing scenarios passed.');
