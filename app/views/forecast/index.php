<?php
$defaultOrigin = null;
$warehouse = null;
foreach ($branches as $branch) {
    if ($branch['is_origin']) {
        $defaultOrigin = (int) $branch['id'];
        $warehouse = $branch;
        break;
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
        <h1 class="display-6 fw-bold mb-1">Route Forecast</h1>
        <p class="text-secondary mb-0">Find branches along a truck route and preview the time added by extra stops.</p>
    </div>
</div>
<?php if (count($branches) < 2): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><?= $warehouse ? 'Add a delivery branch to plan a route from the warehouse.' : 'Add your warehouse and delivery branches to start planning routes.' ?></span>
        <?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('branch_form')) ?>">Add branch</a><?php endif; ?>
    </div>
<?php endif; ?>

<div class="forecast-layout">
    <section class="forecast-route">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Plan a route</h2>
            <div class="mb-3">
                <label for="originBranch" class="form-label fw-semibold">Origin</label>
                <select id="originBranch" class="form-select" <?= !$branches ? 'disabled' : '' ?>>
                    <option value="">Choose an origin</option>
                    <?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= (int) $branch['id'] === $defaultOrigin ? 'selected' : '' ?>><?= e($branch['code'] . ' · ' . $branch['name']) ?></option><?php endforeach; ?>
                </select>
                <?php if ($warehouse): ?><p class="form-text">Warehouse address <?= e($warehouse['address']) ?></p><?php endif; ?>
            </div>
            <div class="mb-4">
                <label for="destinationBranch" class="form-label fw-semibold">Main destination</label>
                <select id="destinationBranch" class="form-select" <?= count($branches) < 2 ? 'disabled' : '' ?>>
                    <option value="">Choose a delivery branch</option>
                    <?php foreach (count($branches) >= 2 ? $branches : [] as $branch): ?><option value="<?= (int) $branch['id'] ?>"><?= e($branch['code'] . ' · ' . $branch['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="corridorKm" class="form-label fw-semibold mb-0">Route corridor</label>
                <strong id="corridorValue" class="text-primary">1.5 km</strong>
            </div>
            <input id="corridorKm" class="form-range" type="range" min="0.5" max="5" step="0.1" value="1.5">
            <div class="d-flex justify-content-between text-secondary small"><span>0.5 km</span><span>5 km</span></div>
        </div></div>
        <div id="forecastMessage" class="alert alert-info mt-3 mb-0" role="status" aria-live="polite">Choose a delivery branch to preview the route.</div>
    </section>

    <section class="forecast-preview card border-0 shadow-sm"><div class="card-body p-3">
        <h2 class="h5 fw-bold mb-3">Route map</h2>
        <div id="forecastMap" class="map forecast-map"
             data-branches="<?= e(json_encode($mapBranches, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>"
             data-proxy-url="<?= e(url('route_proxy')) ?>"
             data-save-url="<?= e(url('trip_save')) ?>"
             data-csrf="<?= e(csrf_token()) ?>"
             data-recommended-max="<?= e((string) $recommendedMax) ?>"
             data-marginal-max="<?= e((string) $marginalMax) ?>"></div>
        <div class="map-legend small text-secondary mt-3"><span><span class="legend-line direct"></span> Direct route</span><span><span class="legend-line final"></span> Recalculated route</span><span><span class="legend-dot active"></span> Candidate branch</span><span><span class="legend-dot origin"></span> Selected stop</span></div>
    </div></section>

    <section class="forecast-candidates card border-0 shadow-sm"><div class="card-body p-4">
        <h2 class="h5 fw-bold mb-2">Branches along the route <span id="candidateCount" class="badge text-bg-light border ms-1">0</span></h2>
        <p id="candidateHint" class="text-secondary small mb-3">Detours are estimated for up to 10 branches nearest the route.</p>
        <div id="candidateList" class="candidate-list"><div class="app-empty">Waiting for a route.</div></div>
    </div></section>

        <section class="forecast-summary card border-0 shadow-sm"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Route summary</h2><span id="routeCacheBadge" class="badge text-bg-light d-none">Cached</span></div>
            <div class="summary-row"><span>Direct route</span><strong id="directMetrics">—</strong></div>
            <div class="summary-row"><span>Selected stops</span><strong id="selectedCount">0</strong></div>
            <div class="summary-row border-0 pb-0"><span>Current preview</span><strong id="finalMetrics">—</strong></div>
            <button id="recalculateRoute" class="btn btn-primary w-100 mt-4" type="button" disabled>Recalculate with selected stops</button>
            <p id="recalculateStatus" class="small mt-2 mb-0" role="status"></p>
        </div></section>

        <section class="forecast-save card border-0 shadow-sm"><div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Save trip</h2>
            <p class="small mb-3"><a href="<?= e(url('load_planner')) ?>">Check truck load capacity →</a></p>
            <form id="saveTripForm">
                <div class="mb-3"><label for="tripTruck" class="form-label fw-semibold">Truck</label><select id="tripTruck" class="form-select" required><option value="">Choose truck</option><?php foreach ($trucks as $truck): ?><option value="<?= (int) $truck['id'] ?>"><?= e($truck['plate_no'] . ' · ' . ($truck['body_type'] ?: $truck['name'])) ?> (<?= e(number_format((float) $truck['capacity_kg'], 0)) ?> kg)</option><?php endforeach; ?></select></div>
                <div class="mb-3"><label for="tripDriver" class="form-label fw-semibold">Driver</label><input id="tripDriver" type="text" class="form-control" maxlength="120" placeholder="Driver's full name" autocapitalize="words" required></div>
                <div class="mb-3"><label for="tripDate" class="form-label fw-semibold">Trip date</label><input id="tripDate" type="date" class="form-control" value="<?= e($today) ?>" required></div>
                <button id="saveTripButton" type="submit" class="btn btn-primary w-100" disabled>Save planned trip</button>
                <p id="saveTripStatus" class="small mt-2 mb-0" role="status"></p>
                <?php if (!$trucks): ?><p class="text-secondary small mt-2 mb-0">An active truck is required. Ask an admin to add one.</p><?php endif; ?>
            </form>
        </div></section>
</div>
<?php $pageModule = 'forecast.js?v=mobile1'; ?>
