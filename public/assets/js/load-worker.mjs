import {planLoad, packageCapacity} from './stop-packing.mjs?v=load2';

self.addEventListener('message', ({data}) => {
    try {
        const {type, inputs, result, example} = data;
        const {truck, clearance, lines, stops} = inputs;
        const value = type === 'capacity'
            ? packageCapacity(truck, clearance, lines, stops, result, example)
            : planLoad(truck, clearance, lines, stops);
        self.postMessage({value});
    } catch (error) {
        self.postMessage({error: error.message});
    }
});
