<?php
$truckData = array_map(static fn(array $truck): array => [
    'id' => (int) $truck['id'],
    'plate' => $truck['plate_no'],
    'name' => $truck['name'],
    'capacity' => (float) $truck['capacity_kg'],
    'length' => $truck['cargo_length_cm'] === null ? null : (float) $truck['cargo_length_cm'],
    'width' => $truck['cargo_width_cm'] === null ? null : (float) $truck['cargo_width_cm'],
    'height' => $truck['cargo_height_cm'] === null ? null : (float) $truck['cargo_height_cm'],
    'doorWidth' => $truck['door_width_cm'] === null ? null : (float) $truck['door_width_cm'],
    'doorHeight' => $truck['door_height_cm'] === null ? null : (float) $truck['door_height_cm'],
], $trucks);
$boxData = array_map(static fn(array $box): array => [
    'id' => (int) $box['id'],
    'code' => $box['code'],
    'name' => $box['name'],
    'shape' => $box['shape'],
    'length' => (float) $box['length_cm'],
    'width' => (float) $box['width_cm'],
    'height' => (float) $box['height_cm'],
    'weight' => (float) $box['weight_kg'],
    'canTilt' => (bool) $box['can_tilt'],
], $boxes);
?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><div class="eyebrow mb-2">CAPACITY PLANNING</div><h1 class="display-6 fw-bold mb-1">Load planner</h1><p class="text-secondary mb-0">Estimate mixed boxes that fit a truck and see what else could be added.</p></div>
    <a class="btn btn-outline-primary" href="<?= e(url('boxes')) ?>">Box types</a>
</div>
<div id="loadPlanner" data-trucks="<?= e(json_encode($truckData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>" data-boxes="<?= e(json_encode($boxData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Truck and clearance</h2>
                <label for="loadTruck" class="form-label fw-semibold">Truck</label>
                <select id="loadTruck" class="form-select mb-3"><option value="">Choose a truck</option><?php foreach ($trucks as $truck): ?><option value="<?= (int) $truck['id'] ?>" <?= (int) $truck['id'] === $selectedTruck ? 'selected' : '' ?>><?= e($truck['plate_no'] . ' · ' . $truck['name']) ?></option><?php endforeach; ?><option value="custom" <?= !$trucks ? 'selected' : '' ?>>Custom truck dimensions…</option></select>
                <div id="truckMeasurements" class="small text-secondary mb-3">Select a truck to see its usable space.</div>
                <div id="customTruckFields" class="row g-2 mb-3 d-none">
                    <div class="col-12 small text-secondary">Enter measured clear interior dimensions. A nominal 16 ft body is 487.68 cm long, but its usable interior may be shorter.</div>
                    <div class="col-4"><label class="form-label small" for="customTruckLength">Length (cm)</label><input id="customTruckLength" class="form-control custom-truck-field" type="number" min="0.01" max="2000" step="0.01"></div>
                    <div class="col-4"><label class="form-label small" for="customTruckWidth">Width (cm)</label><input id="customTruckWidth" class="form-control custom-truck-field" type="number" min="0.01" max="2000" step="0.01"></div>
                    <div class="col-4"><label class="form-label small" for="customTruckHeight">Height (cm)</label><input id="customTruckHeight" class="form-control custom-truck-field" type="number" min="0.01" max="2000" step="0.01"></div>
                    <div class="col-4"><label class="form-label small" for="customTruckCapacity">Payload (kg)</label><input id="customTruckCapacity" class="form-control custom-truck-field" type="number" min="0.01" step="0.01"></div>
                    <div class="col-4"><label class="form-label small" for="customDoorWidth">Door width (cm)</label><input id="customDoorWidth" class="form-control custom-truck-field" type="number" min="0.01" max="2000" step="0.01"></div>
                    <div class="col-4"><label class="form-label small" for="customDoorHeight">Door height (cm)</label><input id="customDoorHeight" class="form-control custom-truck-field" type="number" min="0.01" max="2000" step="0.01"></div>
                    <div class="col-12 form-text">Door opening is optional; enter both measurements or leave both blank.</div>
                </div>
                <label for="wallClearance" class="form-label fw-semibold">Wall and roof clearance (cm)</label>
                <input id="wallClearance" type="number" min="0" max="50" step="0.5" value="2" class="form-control" style="max-width: 160px">
                <div class="form-text">Reserved on both sides and at the front/rear; also deducted from clear door width and height.</div>
            </div></div>
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><h2 class="h5 fw-bold mb-0">Planned goods</h2><button id="addLoadRow" type="button" class="btn btn-sm btn-outline-primary">+ Add item</button></div>
                <p class="text-secondary small">Choose a saved package or enter one-off dimensions. The planner fills larger packages first.</p>
                <div id="loadRows"></div>
                <button id="calculateLoad" type="button" class="btn btn-primary w-100 mt-3">Estimate load</button>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div id="loadMessage" class="alert alert-info" role="status" aria-live="polite">Choose a truck and add goods to estimate a load.</div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6"><div class="card border-0 shadow-sm metric-card"><div class="card-body p-4"><div class="text-secondary small">Boxes placed</div><div id="placedMetric" class="metric-value">—</div><div id="placedSub" class="small text-secondary">of requested quantity</div></div></div></div>
                <div class="col-sm-6"><div class="card border-0 shadow-sm metric-card"><div class="card-body p-4"><div class="text-secondary small">Payload used</div><div id="weightMetric" class="metric-value">—</div><div id="weightSub" class="small text-secondary">of truck capacity</div></div></div></div>
            </div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <div class="d-flex justify-content-between gap-2"><h2 class="h5 fw-bold">Space used</h2><strong id="spacePercent">—</strong></div>
                <div class="progress mb-3" role="progressbar" aria-label="Cargo envelope volume used"><div id="spaceBar" class="progress-bar" style="width: 0%"></div></div>
                <div id="spaceText" class="small text-secondary"></div>
                <div class="d-flex justify-content-between align-items-center mt-4 mb-2"><h3 class="h6 fw-bold mb-0">Floor view</h3><span class="text-secondary small">first layer only</span></div>
                <div id="loadFloor" class="load-floor"><div class="text-secondary small text-center py-5">No load calculated.</div></div>
                <div class="d-flex justify-content-between small text-secondary mt-1"><span>Cab end</span><span>Rear door</span></div>
                <div id="floorLegend" class="small mt-3"></div>
            </div></div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-2">Planned goods result</h2>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Package</th><th class="text-end">Requested</th><th class="text-end">Placed</th><th>Note</th></tr></thead><tbody id="plannedResult"><tr><td colspan="4" class="text-secondary py-3">No calculation yet.</td></tr></tbody></table></div>
            </div></div>
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-2">What else could fit?</h2>
                <p class="text-secondary small">Each extra quantity is estimated separately on top of the planned load. These quantities cannot all be added together.</p>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Package</th><th>Size</th><th class="text-end">Extra quantity</th></tr></thead><tbody id="extraResult"><tr><td colspan="3" class="text-secondary py-3">No calculation yet.</td></tr></tbody></table></div>
            </div></div>
        </div>
    </div>
    <p class="text-secondary small mt-4 mb-0">Planning estimate only. The layout treats cylinders as square footprints and assumes stable stacking and a clear rectangular cargo bay. Check wheel wells, floor/axle limits, loading order, securing space, and package strength before dispatch.</p>
</div>
<template id="loadRowTemplate"><div class="load-item-row border rounded-3 p-3 mb-2"><div class="row g-2 align-items-end"><div class="col-sm-7"><label class="form-label small fw-semibold">Package type</label><select class="form-select load-box-select"><option value="">Choose package</option><?php foreach ($boxes as $box): ?><option value="<?= (int) $box['id'] ?>"><?= e($box['code'] . ' · ' . $box['name']) ?></option><?php endforeach; ?><option value="custom">Custom dimensions…</option></select></div><div class="col-6 col-sm-3"><label class="form-label small fw-semibold">Quantity</label><input class="form-control load-quantity" type="number" min="1" max="10000" step="1" value="1"></div><div class="col-6 col-sm-2"><button type="button" class="btn btn-outline-danger w-100 load-remove" aria-label="Remove item">×</button></div></div><div class="load-box-details small text-secondary mt-2"></div><div class="load-custom-fields d-none mt-3 pt-3 border-top"><div class="row g-2"><div class="col-sm-7"><label class="form-label small">Item name</label><input class="form-control custom-name" maxlength="80" placeholder="e.g. appliance carton"></div><div class="col-sm-5"><label class="form-label small">Shape</label><select class="form-select custom-shape"><option value="Box">Rectangular box</option><option value="Cylinder">Upright cylinder</option></select></div><div class="col-4"><label class="form-label small custom-length-label">Length (cm)</label><input class="form-control custom-length" type="number" min="0.01" max="2000" step="0.01"></div><div class="col-4 custom-width-wrap"><label class="form-label small">Width (cm)</label><input class="form-control custom-width" type="number" min="0.01" max="2000" step="0.01"></div><div class="col-4"><label class="form-label small">Height (cm)</label><input class="form-control custom-height" type="number" min="0.01" max="2000" step="0.01"></div><div class="col-sm-6"><label class="form-label small">Weight each (kg)</label><input class="form-control custom-weight" type="number" min="0.001" step="0.001"></div><div class="col-sm-6 align-self-end"><div class="form-check mb-2"><input class="form-check-input custom-tilt" type="checkbox"><label class="form-check-label small">May be placed on its side</label></div></div></div></div></div></template>
<?php $pageModule = 'load-planner.mjs'; ?>
