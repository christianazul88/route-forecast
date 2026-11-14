<?php
$defaultOrigin = null;
$defaultDestination = null;
foreach ($branches as $branch) {
    if ($branch['is_origin']) {
        $defaultOrigin = (int) $branch['id'];
    }
    if ($branch['code'] === 'VAL-01') {
        $defaultDestination = (int) $branch['id'];
    }
}
if ($defaultOrigin === null && $branches) {
    $defaultOrigin = (int) $branches[0]['id'];
}
if ($defaultDestination === null) {
    foreach ($branches as $branch) {
        if ((int) $branch['id'] !== $defaultOrigin) {
            $defaultDestination = (int) $branch['id'];
            break;
        }
    }
}
$mapBranches = array_map(static fn(array $branch): array => [
    'id' => (int) $branch['id'],
    'code' => $branch['code'],
    'name' => $branch['name'],
    'address' => $branch['address'],
    'lat' => (float) $branch['lat'],
    'lng' => (float) $branch['lng'],
    'is_origin' => (bool) $branch['is_origin'],
], $branches);
?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <div class="eyebrow mb-2">TRIP PLANNING</div>
        <h1 class="display-6 fw-bold mb-1">Route Forecast</h1>
        <p class="text-secondary mb-0">Find branches along a truck route and preview the time added by extra stops.</p>
    </div>
    <span class="badge rounded-pill text-bg-light border px-3 py-2">Guiguinto network · <?= count($branches) ?> active branches</span>
</div>

<div class="row g-4 forecast-layout">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Plan a route</h2>
            <div class="mb-3">
                <label for="originBranch" class="form-label fw-semibold">Origin</label>
                <select id="originBranch" class="form-select" <?= count($branches) < 2 ? 'disabled' : '' ?>>
                    <?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) $branch['id'] === $defaultOrigin ? 'selected' : '' ?>><?= e($branch['code'] . ' · ' . $branch['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="destinationBranch" class="form-label fw-semibold">Main destination</label>
                <select id="destinationBranch" class="form-select" <?= count($branches) < 2 ? 'disabled' : '' ?>>
                    <?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) $branch['id'] === $defaultDestination ? 'selected' : '' ?>><?= e($branch['code'] . ' · ' . $branch['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="corridorKm" class="form-label fw-semibold mb-0">Route corridor</label>
                <strong id="corridorValue" class="text-primary">1.5 km</strong>
            </div>
            <input id="corridorKm" class="form-range" type="range" min="0.5" max="5" step="0.1" value="1.5">
            <div class="d-flex justify-content-between text-secondary small"><span>0.5 km</span><span>5 km</span></div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Route summary</h2><span id="routeCacheBadge" class="badge text-bg-light d-none">Cached</span></div>
            <div class="summary-row"><span>Direct route</span><strong id="directMetrics">—</strong></div>
            <div class="summary-row"><span>Selected stops</span><strong id="selectedCount">0</strong></div>
            <div class="summary-row border-0 pb-0"><span>Current preview</span><strong id="finalMetrics">—</strong></div>
            <button id="recalculateRoute" class="btn btn-primary w-100 mt-4" type="button" disabled>Recalculate with selected stops</button>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Save trip</h2>
            <p class="small mb-3"><a href="<?= e(url('load_planner')) ?>">Check truck load capacity →</a></p>
            <form id="saveTripForm">
                <div class="mb-3"><label for="tripTruck" class="form-label fw-semibold">Truck</label><select id="tripTruck" class="form-select" required><option value="">Choose truck</option><?php foreach ($trucks as $truck): ?><option value="<?= (int) $truck['id'] ?>"><?= e($truck['plate_no'] . ' · ' . $truck['name']) ?> (<?= e(number_format((float) $truck['capacity_kg'], 0)) ?> kg)</option><?php endforeach; ?></select></div>
                <div class="mb-3"><label for="tripDriver" class="form-label fw-semibold">Driver</label><input id="tripDriver" type="text" class="form-control" maxlength="120" placeholder="Driver's full name" required></div>
                <div class="mb-3"><label for="tripDate" class="form-label fw-semibold">Trip date</label><input id="tripDate" type="date" class="form-control" value="<?= e($today) ?>" required></div>
                <button id="saveTripButton" type="submit" class="btn btn-primary w-100" disabled>Save planned trip</button>
                <?php if (!$trucks): ?><p class="text-secondary small mt-2 mb-0">An active truck is required. Ask an admin to add one.</p><?php endif; ?>
            </form>
        </div></div>

        <div id="forecastMessage" class="alert alert-info mb-0" role="status" aria-live="polite">Loading the direct route…</div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body p-3">
            <div id="forecastMap" class="map forecast-map"
                 data-branches="<?= e(json_encode($mapBranches, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>"
                 data-proxy-url="<?= e(url('route_proxy')) ?>"
                 data-save-url="<?= e(url('trip_save')) ?>"
                 data-csrf="<?= e(csrf_token()) ?>"
                 data-recommended-max="<?= e((string) $recommendedMax) ?>"
                 data-marginal-max="<?= e((string) $marginalMax) ?>"></div>
        </div></div>
        <div class="d-flex flex-wrap gap-3 small text-secondary mb-4"><span><span class="legend-line direct"></span> Direct route</span><span><span class="legend-line final"></span> Recalculated route</span><span><span class="legend-dot active"></span> Candidate branch</span><span><span class="legend-dot origin"></span> Selected stop</span></div>

        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                <h2 class="h5 fw-bold mb-0">Branches along the route <span id="candidateCount" class="badge text-bg-light border ms-1">0</span></h2>
                <span class="text-secondary small">Sorted by position from origin</span>
            </div>
            <p id="candidateHint" class="text-secondary small mb-3">Detours are estimated for up to 10 branches nearest the route.</p>
            <div id="candidateList" class="candidate-list"><div class="text-secondary py-4 text-center">Waiting for a route.</div></div>
        </div></div>
    </div>
</div>
<?php $pageModule = 'forecast.js'; ?>
