<?php
$trip = $detail['trip'];
$stops = $detail['stops'];
$nextStatuses = match ($trip['status']) {
    'Planned' => ['Dispatched', 'Cancelled'],
    'Dispatched' => ['Completed', 'Cancelled'],
    default => [],
};
$mapStops = array_map(static fn(array $stop): array => [
    'order' => (int) $stop['stop_order'], 'name' => $stop['name'], 'code' => $stop['code'],
    'lat' => (float) $stop['lat'], 'lng' => (float) $stop['lng'], 'is_main' => (bool) $stop['is_main_destination'],
], $stops);
?>
<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><a class="text-decoration-none small" href="<?= e(url('trips')) ?>">← Back to trips</a><h1 class="display-6 fw-bold mb-1"><?= e($trip['trip_no']) ?></h1><p class="text-secondary mb-0"><?= e(date('F j, Y', strtotime($trip['trip_date']))) ?> · <?= e($trip['origin_name']) ?> → <?= e($trip['destination_name']) ?></p></div>
    <div class="page-actions d-flex flex-wrap gap-2"><a class="btn btn-primary" href="<?= e(url('load_planner', ['trip_id' => $trip['id']])) ?>"><i class="bi bi-boxes" aria-hidden="true"></i> Load plan</a><a class="btn btn-outline-primary" href="<?= e(url('fuel', ['trip_id' => $trip['id']])) ?>"><i class="bi bi-fuel-pump" aria-hidden="true"></i> Fuel estimate</a><a class="btn btn-outline-primary" href="<?= e(url('trip_sheet', ['id' => $trip['id']])) ?>"><i class="bi bi-printer" aria-hidden="true"></i> Print trip sheet</a><a class="btn btn-outline-primary" href="<?= e(url('trip_csv', ['id' => $trip['id']])) ?>"><i class="bi bi-download" aria-hidden="true"></i> Export CSV</a></div>
</div>
<div class="row g-4 mb-4">
    <div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body p-3"><div id="savedRouteMap" class="map detail-map"
        data-geometry="<?= e($trip['route_geometry']) ?>"
        data-origin="<?= e(json_encode(['name' => $trip['origin_name'], 'code' => $trip['origin_code'], 'lat' => (float) $trip['origin_lat'], 'lng' => (float) $trip['origin_lng']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>"
        data-stops="<?= e(json_encode($mapStops, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>"></div></div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Trip details</h2><span class="badge <?= e(status_badge_class($trip['status'])) ?>"><?= e($trip['status']) ?></span></div>
        <div class="summary-row"><span>Truck</span><strong><?= e($trip['plate_no']) ?></strong></div>
        <div class="summary-row"><span>Driver</span><strong><?= e($trip['driver_name']) ?></strong></div>
        <div class="summary-row"><span>Extra stops</span><strong><?= max(0, count($stops) - 1) ?></strong></div>
        <div class="summary-row"><span>Corridor</span><strong><?= e(number_format((float) $trip['corridor_km'], 1)) ?> km</strong></div>
        <div class="summary-row"><span>Total distance</span><strong><?= e(number_format((float) $trip['total_distance_km'], 1)) ?> km</strong></div>
        <div class="summary-row"><span>Estimated drive time</span><strong><?= e(number_format((float) $trip['total_duration_min'], 0)) ?> min</strong></div>
        <div class="small text-secondary mt-3">Created by <?= e($trip['created_by_name']) ?> on <?= e($trip['created_at']) ?></div>
        <?php if ($nextStatuses): ?><form method="post" action="<?= e(url('trip_status')) ?>" class="mt-4 border-top pt-3">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $trip['id'] ?>">
            <label for="status" class="form-label fw-semibold">Change status</label>
            <div class="d-flex gap-2"><select id="status" name="status" class="form-select"><?php foreach ($nextStatuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Update</button></div>
        </form><?php endif; ?>
    </div></div></div>
</div>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="px-4 py-3 border-bottom"><h2 class="h5 fw-bold mb-0">Stop order</h2></div><div class="table-responsive"><table class="table align-middle mb-0 stacked-table" role="table"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">#</th><th scope="col" role="columnheader">Branch</th><th scope="col" role="columnheader">Contact</th><th scope="col" role="columnheader">Leg from previous</th><th scope="col" role="columnheader">Extra detour</th></tr></thead><tbody role="rowgroup">
    <tr role="row"><td role="cell" data-label="Stop">0</td><td role="cell" class="record-main"><strong><?= e($trip['origin_name']) ?></strong><div class="small text-secondary"><?= e($trip['origin_address']) ?></div><span class="badge text-bg-info">Origin</span></td><td role="cell" data-label="Contact"><?= e($trip['origin_contact_person'] ?: '—') ?><div class="small text-secondary"><?= e($trip['origin_contact_no']) ?></div></td><td role="cell" data-label="Leg from previous">—</td><td role="cell" data-label="Extra detour">—</td></tr>
    <?php foreach ($stops as $stop): ?><tr role="row"><td role="cell" data-label="Stop"><?= (int) $stop['stop_order'] ?></td><td role="cell" class="record-main"><strong><?= e($stop['name']) ?></strong><div class="small text-secondary"><?= e($stop['address']) ?></div><?php if ($stop['is_main_destination']): ?><span class="badge text-bg-primary">Main destination</span><?php endif; ?></td><td role="cell" data-label="Contact"><?= e($stop['contact_person'] ?: '—') ?><div class="small text-secondary"><?= e($stop['contact_no']) ?></div></td><td role="cell" data-label="Leg from previous"><?= $stop['leg_distance_km'] === null ? '—' : e(number_format((float) $stop['leg_distance_km'], 1)) . ' km' ?><?php if ($stop['leg_duration_min'] !== null): ?><div class="small text-secondary"><?= e(number_format((float) $stop['leg_duration_min'], 0)) ?> min</div><?php endif; ?></td><td role="cell" data-label="Extra detour"><?= $stop['is_main_destination'] ? '—' : '+' . e(number_format((float) $stop['detour_km'], 1)) . ' km' ?><?php if (!$stop['is_main_destination']): ?><div class="small text-secondary">+<?= e(number_format((float) $stop['detour_min'], 1)) ?> min vs direct</div><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php $pageModule = 'trip-detail.js'; ?>
