<?php
$pageUrl = url('fuel', $trip ? ['trip_id' => $trip['id']] : []);
$value = static fn(string $field): string => e((string) $values[$field]);
$metric = static fn(float|string $number, int $decimals = 2): string => number_format((float) $number, $decimals);
?>
<div class="page-heading mb-4">
    <?php if ($trip): ?><a class="small text-decoration-none" href="<?= e(url('trip_detail', ['id' => $trip['id']])) ?>">← Back to trip</a><?php endif; ?>
    <h1 class="display-6 fw-bold mb-1">Fuel planner</h1>
    <p class="text-secondary mb-0">Estimate truck distance, diesel use, and fuel cost.</p>
</div>

<?php if ($error): ?><p class="alert alert-warning" role="alert"><?= e($error) ?></p><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body p-3 p-sm-4">
            <?php if ($trip): ?>
                <h2 class="h5 fw-bold mb-1"><?= e($trip['trip_no']) ?></h2>
                <p class="text-secondary mb-1"><?= e($trip['origin_name']) ?> → <?= e($trip['destination_name']) ?></p>
                <p class="small mb-0"><?= e($trip['plate_no']) ?> · <?= $metric($trip['total_distance_km']) ?> km outbound, including delivery stops</p>
            <?php elseif ($trips): ?>
                <form method="get">
                    <input type="hidden" name="page" value="fuel">
                    <label class="form-label fw-semibold" for="fuelTrip">Use a saved trip</label>
                    <div class="row g-2">
                        <div class="col-12 col-sm"><select class="form-select" id="fuelTrip" name="trip_id" required>
                            <option value="">Choose a trip</option>
                            <?php foreach ($trips as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"><?= e($item['trip_no'] . ' · ' . $item['destination_name']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                        <div class="col-12 col-sm-auto"><button class="btn btn-outline-primary w-100">Open trip</button></div>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="h5 fw-bold mb-1">Quick estimate</h2>
                <p class="text-secondary mb-0">Enter your own distances. Saved trips can use the truck route for the return leg.</p>
            <?php endif; ?>
        </div></div>

        <div class="card border-0 shadow-sm"><div class="card-body p-3 p-sm-4">
            <form id="fuelForm" method="post" action="<?= e($pageUrl) ?>" data-has-trip="<?= $trip ? '1' : '0' ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <?php if (!$trip): ?>
                        <div class="col-sm-6"><label for="outboundKm" class="form-label">Outbound distance (km)</label>
                            <input id="outboundKm" name="outbound_km" class="form-control" type="number" inputmode="decimal" min=".01" max="100000" step=".01" value="<?= $value('outbound_km') ?>" required></div>
                    <?php endif; ?>
                    <div class="col-sm-6"><label for="outboundEfficiency" class="form-label">Outbound efficiency (km/L)</label>
                        <input id="outboundEfficiency" name="outbound_efficiency" class="form-control" type="number" inputmode="decimal" min=".1" max="100" step=".01" value="<?= $value('outbound_efficiency') ?>" required>
                        <?php if ($truck && $truck['diesel_km_per_liter'] !== null): ?><div class="form-text">Uses the truck’s saved efficiency initially.</div><?php endif; ?>
                    </div>
                    <div class="col-sm-6"><label for="fuelMode" class="form-label">After the last delivery</label>
                        <select id="fuelMode" name="mode" class="form-select" required>
                            <option value="">Choose a return plan</option>
                            <option value="one_way" <?= $values['mode'] === 'one_way' ? 'selected' : '' ?>>One way</option>
                            <option value="warehouse" <?= $values['mode'] === 'warehouse' ? 'selected' : '' ?>>Return to warehouse</option>
                        </select>
                    </div>
                    <div class="col-sm-6" id="fuelReturnMethod"><label for="returnMethod" class="form-label">Return distance source</label>
                        <select id="returnMethod" name="method" class="form-select">
                            <?php if ($trip): ?><option value="ors" <?= $values['method'] === 'ors' ? 'selected' : '' ?>>Calculate truck route</option><?php endif; ?>
                            <option value="manual" <?= $values['method'] === 'manual' ? 'selected' : '' ?>>Enter known distance</option>
                        </select>
                        <?php if ($trip && $warehouse): ?><div class="form-text">From the final delivery to <?= e($warehouse['name']) ?>.</div><?php endif; ?>
                    </div>
                    <div class="col-sm-6" id="fuelReturnKm"><label for="manualKm" class="form-label">Return distance (km)</label>
                        <input id="manualKm" name="manual_km" class="form-control" type="number" inputmode="decimal" min="0" max="100000" step=".01" value="<?= $value('manual_km') ?>"></div>
                    <div class="col-sm-6" id="fuelReturnEfficiency"><label for="returnEfficiency" class="form-label">Return efficiency (km/L)</label>
                        <input id="returnEfficiency" name="return_efficiency" class="form-control" type="number" inputmode="decimal" min=".1" max="100" step=".01" value="<?= $value('return_efficiency') ?>">
                        <div class="form-text">Optional. Leave blank to use outbound efficiency.</div></div>
                    <div class="col-12 pt-2"><h2 class="h6 fw-bold mb-0">Diesel price</h2></div>
                    <div class="col-sm-6"><label for="fuelPrice" class="form-label">Price (₱/L)</label>
                        <input id="fuelPrice" name="price" class="form-control" type="number" inputmode="decimal" min=".01" max="1000" step=".01" value="<?= $value('price') ?>" required></div>
                    <div class="col-sm-6"><label for="fuelPriceDate" class="form-label">Date observed</label>
                        <input id="fuelPriceDate" name="price_date" class="form-control" type="date" max="<?= e($today) ?>" value="<?= $value('price_date') ?>" required></div>
                    <div class="col-12"><label for="fuelStation" class="form-label">Station or price source</label>
                        <input id="fuelStation" name="station" class="form-control" maxlength="150" value="<?= $value('station') ?>" placeholder="Station and town, or published source" required></div>
                    <div class="col-12 d-grid d-sm-flex gap-2 pt-2">
                        <button class="btn btn-primary" type="submit" name="action" value="estimate">Calculate estimate</button>
                        <?php if ($canSave): ?><button class="btn btn-outline-primary" type="submit" name="action" value="save">Save fuel estimate to trip</button><?php endif; ?>
                    </div>
                </div>
            </form>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3" id="fuelResults"><div class="card-body p-3 p-sm-4">
            <h2 class="h5 fw-bold mb-3"><?= $isSaved ? 'Saved fuel estimate' : 'Fuel estimate' ?></h2>
            <?php if ($result): ?>
                <dl class="mb-0">
                    <div class="summary-row"><dt>Outbound</dt><dd class="mb-0 text-end"><?= $metric($result['outbound_km']) ?> km · <?= $metric($result['outbound_liters']) ?> L</dd></div>
                    <div class="summary-row"><dt>Return</dt><dd class="mb-0 text-end">
                        <?= $result['return_km'] === null ? 'One way' : $metric($result['return_km']) . ' km · ' . $metric($result['return_liters']) . ' L' ?>
                        <?php if ($result['return_km'] !== null): ?>
                            <div class="small text-secondary">To <?= e($result['return_destination']) ?> · <?= $result['return_source'] === 'ors' ? 'Truck route' : 'Entered distance' ?><?= $result['return_minutes'] !== null ? ' · ' . (int) $result['return_minutes'] . ' min driving' : '' ?></div>
                        <?php endif; ?>
                    </dd></div>
                    <div class="summary-row"><dt>Total travel</dt><dd class="mb-0"><?= $metric($result['total_km']) ?> km</dd></div>
                    <div class="summary-row"><dt>Estimated diesel</dt><dd class="mb-0"><?= $metric($result['total_liters']) ?> L</dd></div>
                    <div class="summary-row border-0"><dt>Estimated cost</dt><dd class="mb-0 fw-bold fs-4">₱<?= $metric($result['total_cost_php']) ?></dd></div>
                </dl>
                <p class="small text-secondary mb-0">₱<?= $metric($result['values']['price']) ?>/L from <?= e($result['values']['station']) ?>, observed <?= e($result['values']['price_date']) ?>.</p>
            <?php else: ?>
                <p class="text-secondary mb-0">Calculate an estimate to see distance, liters, and cost.</p>
            <?php endif; ?>
        </div></div>
        <div class="card border-0 shadow-sm"><div class="card-body p-3 p-sm-4">
            <h2 class="h5 fw-bold mb-2">Team diesel price</h2>
            <?php if ($latest): ?>
                <p class="mb-1"><strong>₱<?= $metric($latest['php_per_liter']) ?>/L</strong> · <?= e($latest['station']) ?></p>
                <p class="small text-secondary">Observed <?= e($latest['observed_on']) ?><?= $latest['observed_on'] < $today ? ' · Check the current pump price before using it.' : '' ?></p>
            <?php else: ?>
                <p class="text-secondary mb-2">No station price recorded yet.</p>
            <?php endif; ?>
            <p class="small"><a href="https://doe.gov.ph/data-and-prices/liquid-fuels/retail-pump-prices/north-luzon-pump-prices" target="_blank" rel="noopener noreferrer">DOE North Luzon pump price reports</a></p>
            <?php if (current_user()['role'] === 'Admin'): ?>
                <details>
                    <summary class="fw-semibold">Record a team price</summary>
                    <form method="post" action="<?= e(url('diesel_price_save')) ?>" class="mt-3 d-grid gap-2">
                        <?= csrf_field() ?>
                        <label>Price (₱/L)<input name="price" class="form-control" type="number" inputmode="decimal" min=".01" max="1000" step=".01" required></label>
                        <label>Date observed<input name="price_date" class="form-control" type="date" value="<?= e($today) ?>" max="<?= e($today) ?>" required></label>
                        <label>Station and location<input name="station" class="form-control" maxlength="150" required></label>
                        <button type="submit" class="btn btn-outline-primary">Record price</button>
                    </form>
                </details>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php $pageScript = 'fuel.js?v=1'; ?>
