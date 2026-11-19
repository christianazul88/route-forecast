<?php
$truckData = array_map([Truck::class, 'loadProfile'], $trucks);
$demo = !$detail && ($_GET['demo'] ?? '') === '1' && count($demoStops) === 2;
$tripData = $detail ? [
    'id' => (int) $detail['trip']['id'], 'number' => $detail['trip']['trip_no'], 'status' => $detail['trip']['status'],
    'stops' => $context['stops'], 'hash' => $context['hash'],
    'saved' => $saved ? [
        'payload' => $saved['payload'], 'revision' => (int) $saved['revision'],
        'current' => hash_equals($context['hash'], $saved['source_hash']),
    ] : null,
] : null;
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
    <div><h1 class="display-6 fw-bold mb-1">Load planner</h1><p class="text-secondary mb-0">Plan what fits, the loading order, and which doors to use at each stop.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!$detail && count($demoStops) === 2): ?><a class="btn btn-outline-primary" href="<?= e(url('load_planner', $demo ? [] : ['demo' => 1])) ?>"><?= $demo ? 'Start blank' : 'Try demo load' ?></a><?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('boxes')) ?>">Box types</a>
    </div>
</div>
<?php if ($demo): ?><div class="alert alert-info">Training load for Bocaue and Valenzuela. Switch between the rear-only demo van, the two-door van, and the wing van to compare.</div><?php endif; ?>
<div id="loadPlanner"
    data-trucks="<?= e(json_encode($truckData, JSON_THROW_ON_ERROR)) ?>"
    data-boxes="<?= e(json_encode($boxData, JSON_THROW_ON_ERROR)) ?>"
    data-trip="<?= e(json_encode($tripData, JSON_THROW_ON_ERROR)) ?>"
    data-demo="<?= e(json_encode($demo ? $demoStops : null, JSON_THROW_ON_ERROR)) ?>"
    data-endpoint="<?= e(url('load_plan_save')) ?>"
    data-csrf="<?= e(csrf_token()) ?>">
    <div class="row g-4">
        <div class="col-lg-5 min-w-0">
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-3 p-sm-4">
                <h2 class="h5 fw-bold mb-2">Trip</h2>
                <?php if ($detail): ?>
                    <a href="<?= e(url('trip_detail', ['id' => $detail['trip']['id']])) ?>"><?= e($detail['trip']['trip_no']) ?></a>
                    <p class="small text-secondary mb-0"><?= e($detail['trip']['origin_name']) ?> → <?= e($detail['trip']['destination_name']) ?></p>
                <?php else: ?>
                    <form method="get" class="d-flex flex-wrap gap-2">
                        <input type="hidden" name="page" value="load_planner">
                        <label class="visually-hidden" for="plannedTrip">Planned trip</label>
                        <select id="plannedTrip" name="trip_id" class="form-select flex-grow-1" required>
                            <option value="">Choose a planned trip</option>
                            <?php foreach ($trips as $trip): ?><option value="<?= (int) $trip['id'] ?>"><?= e($trip['trip_no'] . ' · ' . $trip['trip_date']) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary w-100">Open trip</button>
                    </form>
                    <p class="form-text mb-0">You can also estimate a load without a trip.</p>
                <?php endif; ?>
            </div></div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Truck and clearance</h2>
                <label for="loadTruck" class="form-label fw-semibold">Truck</label>
                <select id="loadTruck" class="form-select mb-3" <?= $detail ? 'disabled' : '' ?>><option value="">Choose a truck</option><?php foreach ($trucks as $truck): ?><option value="<?= (int) $truck['id'] ?>" <?= (int) $truck['id'] === $selectedTruck ? 'selected' : '' ?>><?= e($truck['name'] . ' · ' . $truck['plate_no']) ?></option><?php endforeach; ?><option value="custom" <?= !$trucks ? 'selected' : '' ?>>Custom truck dimensions…</option></select>
                <div id="truckMeasurements" class="small text-secondary mb-3">Select a truck to see its usable space.</div>
                <div id="customTruckFields" class="row g-2 mb-3 d-none">
                    <div class="col-12 small text-secondary">Quick estimate with a single rear opening. Save the truck to model multiple openings.</div>
                    <div class="col-4"><label class="form-label small" for="customTruckLength">Length (ft)</label><input id="customTruckLength" class="form-control custom-truck-field" type="number" inputmode="decimal" min="0.01" step="any"></div>
                    <div class="col-4"><label class="form-label small" for="customTruckWidth">Width (ft)</label><input id="customTruckWidth" class="form-control custom-truck-field" type="number" inputmode="decimal" min="0.01" step="any"></div>
                    <div class="col-4"><label class="form-label small" for="customTruckHeight">Height (ft)</label><input id="customTruckHeight" class="form-control custom-truck-field" type="number" inputmode="decimal" min="0.01" step="any"></div>
                    <div class="col-4"><label class="form-label small" for="customTruckCapacity">Payload (kg)</label><input id="customTruckCapacity" class="form-control custom-truck-field" type="number" min="0.01" step="0.01"></div>
                    <div class="col-4"><label class="form-label small" for="customDoorWidth">Door width (ft)</label><input id="customDoorWidth" class="form-control custom-truck-field" type="number" inputmode="decimal" min="0.01" step="any"></div>
                    <div class="col-4"><label class="form-label small" for="customDoorHeight">Door height (ft)</label><input id="customDoorHeight" class="form-control custom-truck-field" type="number" inputmode="decimal" min="0.01" step="any"></div>
                    <div class="col-12 form-text">Leave both door fields blank to assume an open rear face for the estimate.</div>
                </div>
                <label for="wallClearance" class="form-label fw-semibold">Wall and roof clearance (cm)</label>
                <input id="wallClearance" type="number" min="0" max="50" step="0.5" value="2" class="form-control" style="max-width: 160px">
                <div class="form-text">Reserved on both sides and at the front/rear; also deducted from clear door width and height.</div>
            </div></div>
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><h2 class="h5 fw-bold mb-0">Planned goods</h2><button id="addLoadRow" type="button" class="btn btn-sm btn-outline-primary">+ Add item</button></div>
                <p class="text-secondary small">Select the doors available at each stop and assign each item to its delivery.</p>
                <div id="deliveryDoors"></div>
                <div id="loadRows"></div>
                <button id="calculateLoad" type="button" class="btn btn-primary w-100 mt-3">Estimate load</button>
            </div></div>
        </div>
        <div class="col-lg-7 min-w-0" id="estimatePanel">
            <div id="loadMessage" class="alert alert-info" role="status" aria-live="polite">Choose a truck and add goods to estimate a load.</div>
            <?php if ($detail && $detail['trip']['status'] === 'Planned'): ?><button id="saveLoad" type="button" class="btn btn-primary w-100 mb-3" disabled>Save load plan to trip</button><?php endif; ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-6"><div class="card border-0 shadow-sm metric-card"><div class="card-body p-4"><div class="text-secondary small">Items loaded</div><div id="placedMetric" class="metric-value">—</div><div id="placedSub" class="small text-secondary">of requested quantity</div></div></div></div>
                <div class="col-sm-6"><div class="card border-0 shadow-sm metric-card"><div class="card-body p-4"><div class="text-secondary small">Payload used</div><div id="weightMetric" class="metric-value">—</div><div id="weightSub" class="small text-secondary">of truck capacity</div></div></div></div>
            </div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <div class="d-flex justify-content-between gap-2"><h2 class="h5 fw-bold">Space used</h2><strong id="spacePercent">—</strong></div>
                <div class="progress mb-3" role="progressbar" aria-label="Cargo envelope volume used"><div id="spaceBar" class="progress-bar" style="width: 0%"></div></div>
                <div id="spaceText" class="small text-secondary"></div>
                <div id="cargoFilters" class="row g-2 mt-2" hidden>
                    <div class="col-6"><label for="cargoStop" class="form-label small mb-1">Show delivery</label><select id="cargoStop" class="form-select"><option value="">All stops</option></select></div>
                    <div class="col-6"><label for="cargoColor" class="form-label small mb-1">Color by</label><select id="cargoColor" class="form-select"><option value="item">Item type</option><option value="stop">Delivery stop</option></select></div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 mb-2">
                    <h3 class="h6 fw-bold mb-0">Cargo layout</h3>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Cargo view">
                        <button type="button" class="btn btn-outline-primary active" id="view3d" aria-pressed="true">3D view</button>
                        <button type="button" class="btn btn-outline-primary" id="viewFloor" aria-pressed="false">Floor view</button>
                    </div>
                </div>
                <div id="cargo3d">
                    <div class="load-scene">
                        <canvas id="loadCanvas" aria-label="3D cargo bay showing packages at their estimated positions"></canvas>
                        <span class="load-end-label" id="cargoCab" hidden>Cab end</span>
                        <span class="load-end-label" id="cargoRear" hidden>Rear end</span>
                        <div id="cargoPlaceholder" class="load-placeholder small text-secondary">No load calculated.</div>
                    </div>
                    <div id="cargoControls" class="row g-2 mt-1 align-items-end" hidden>
                        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="cargoTurn">Rotation</label><input class="form-range" type="range" min="-180" max="180" value="30" id="cargoTurn"></div>
                        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="cargoTilt">Elevation</label><input class="form-range" type="range" min="12" max="88" value="28" id="cargoTilt"></div>
                        <div class="col-7 col-md-4"><label class="form-label small mb-1" for="cargoLevel">Show through</label><select class="form-select form-select-sm" id="cargoLevel"><option value="">All levels</option></select></div>
                        <div class="col-5 col-md-2"><button type="button" class="btn btn-sm btn-outline-secondary w-100" id="cargoReset">Reset</button></div>
                    </div>
                    <p id="cargoViewNote" class="small text-secondary mt-2 mb-0"></p>
                </div>
                <div id="cargoFloor" hidden>
                    <div id="loadFloor" class="load-floor"><div class="text-secondary small text-center py-5">No load calculated.</div></div>
                    <div class="d-flex justify-content-between small text-secondary mt-1"><span>Cab end</span><span>First layer only</span><span>Rear end</span></div>
                </div>
                <div id="floorLegend" class="small mt-3"></div>
                <div id="cargoInspector" class="mt-3" hidden>
                    <label for="cargoPlacement" class="form-label small fw-semibold mb-1">Inspect a placement</label>
                    <select class="form-select form-select-sm" id="cargoPlacement"></select>
                    <p id="cargoPlacementDetail" class="small text-secondary mt-2 mb-0" aria-live="polite"></p>
                </div>
            </div></div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-2">Planned goods result</h2>
                <div id="plannedResult" class="small text-secondary">No calculation yet.</div>
            </div></div>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Crew instructions</h2>
                <div id="crewInstructions" class="small text-secondary">Estimate a load to see the loading and unloading steps.</div>
            </div></div>
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="h5 fw-bold mb-2">What else could fit?</h2>
                <p class="text-secondary small">Check one package and destination at a time. Mixed estimates keep the units already loaded and may require a different layout.</p>
                <form id="capacityForm" class="d-flex flex-column gap-2">
                    <label for="capacityPackage" class="visually-hidden">Package to check</label>
                    <select id="capacityPackage" class="form-select" required><option value="">Choose a package</option></select>
                    <label for="capacityStop" class="visually-hidden">Delivery stop to check</label>
                    <select id="capacityStop" class="form-select" required></select>
                    <button id="capacityCheck" type="submit" class="btn btn-outline-primary" disabled>Check capacity</button>
                </form>
                <p id="extraResult" class="small mb-0 mt-2" role="status"></p>
            </div></div>
        </div>
    </div>
    <p class="text-secondary small mt-4 mb-0">Planning estimate only. Cylinders use square footprints. Irregular items reserve a rectangular space and stay on the floor with the space above them clear. Check loading order, axle limits, securing space, and package strength before dispatch.</p>
</div>
<template id="loadRowTemplate"><div class="load-item-row border rounded-3 p-3 mb-2"><div class="row g-2 align-items-end"><div class="col-sm-7"><label class="form-label small fw-semibold">Package type</label><select class="form-select load-box-select"><option value="">Choose package</option><?php foreach ($boxes as $box): ?><option value="<?= (int) $box['id'] ?>"><?= e($box['code'] . ' · ' . $box['name']) ?></option><?php endforeach; ?><option value="custom">Custom dimensions…</option></select></div><div class="col-6 col-sm-3"><label class="form-label small fw-semibold">Quantity</label><input class="form-control load-quantity" type="number" min="1" max="10000" step="1" value="1"></div><div class="col-6 col-sm-2"><button type="button" class="btn btn-outline-danger w-100 load-remove" aria-label="Remove item">×</button></div></div><div class="load-box-details small text-secondary mt-2"></div><div class="load-custom-fields d-none mt-3 pt-3 border-top"><div class="row g-2"><div class="col-sm-7"><label class="form-label small">Item name</label><input class="form-control custom-name" maxlength="80" placeholder="e.g. appliance carton"></div><div class="col-sm-5"><label class="form-label small">Shape</label><select class="form-select custom-shape"><?php foreach (BoxType::SHAPES as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-12 small text-secondary custom-shape-hint"></div><div class="col-4"><label class="form-label small custom-length-label">Length (cm)</label><input class="form-control custom-length" type="number" min="0.01" max="2000" step="0.01"></div><div class="col-4 custom-width-wrap"><label class="form-label small">Width (cm)</label><input class="form-control custom-width" type="number" min="0.01" max="2000" step="0.01"></div><div class="col-4"><label class="form-label small">Height (cm)</label><input class="form-control custom-height" type="number" min="0.01" max="2000" step="0.01"></div><div class="col-sm-6"><label class="form-label small">Weight each (kg)</label><input class="form-control custom-weight" type="number" min="0.001" step="0.001"></div><div class="col-sm-6 align-self-end"><div class="form-check mb-2"><input class="form-check-input custom-tilt" type="checkbox"><label class="form-check-label small">May be placed on its side</label></div></div></div></div></div></template>
<?php $pageModule = 'load-planner.mjs?v=load2'; ?>
