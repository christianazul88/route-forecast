<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h1 class="display-6 fw-bold mb-1">Trips</h1><p class="text-secondary mb-0">Browse saved routes and delivery schedules.</p></div>
    <a class="btn btn-primary" href="<?= e(url('forecast')) ?>">+ Plan a route</a>
</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <h2 class="section-heading"><i class="bi bi-funnel text-primary" aria-hidden="true"></i> Filter trips</h2>
    <form method="get" action="<?= e(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php') ?>" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="trips">
        <div class="col-6 col-lg-2"><label for="date_from" class="form-label">From date</label><input id="date_from" name="date_from" type="date" class="form-control" value="<?= e($filters['date_from']) ?>"></div>
        <div class="col-6 col-lg-2"><label for="date_to" class="form-label">To date</label><input id="date_to" name="date_to" type="date" class="form-control" value="<?= e($filters['date_to']) ?>"></div>
        <div class="col-6 col-lg-2"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">All statuses</option><?php foreach (Trip::STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-lg-3"><label for="truck_id" class="form-label">Truck</label><select id="truck_id" name="truck_id" class="form-select"><option value="">All trucks</option><?php foreach ($trucks as $truck): ?><option value="<?= (int) $truck['id'] ?>" <?= $filters['truck_id'] === (int) $truck['id'] ? 'selected' : '' ?>><?= e($truck['plate_no'] . ' · ' . $truck['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-lg-3 form-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="<?= e(url('trips')) ?>">Clear</a></div>
    </form>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-body p-0">
    <div class="px-4 py-3 border-bottom"><strong><?= (int) $listing['total'] ?> trip<?= $listing['total'] === 1 ? '' : 's' ?></strong></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0 stacked-table" role="table"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Trip</th><th scope="col" role="columnheader">Route</th><th scope="col" role="columnheader">Truck</th><th scope="col" role="columnheader">Stops</th><th scope="col" role="columnheader">Distance / time</th><th scope="col" role="columnheader">Status</th><th scope="col" role="columnheader" class="text-end">Open</th></tr></thead><tbody role="rowgroup">
    <?php foreach ($listing['rows'] as $trip): ?><tr role="row">
        <td role="cell" class="text-nowrap" data-label="Trip"><strong><?= e($trip['trip_no']) ?></strong><div class="small text-secondary"><?= e($trip['trip_date']) ?></div></td>
        <td role="cell" class="record-main"><div><?= e($trip['origin_name']) ?> → <?= e($trip['destination_name']) ?></div><div class="small text-secondary">Driver: <?= e($trip['driver_name']) ?></div></td>
        <td role="cell" data-label="Truck"><?= e($trip['plate_no']) ?></td><td role="cell" data-label="Stops"><?= max(0, (int) $trip['extra_stops']) ?> extra</td>
        <td role="cell" class="text-nowrap" data-label="Distance / time"><?= e(number_format((float) $trip['total_distance_km'], 1)) ?> km<br><span class="small text-secondary"><?= e(number_format((float) $trip['total_duration_min'], 0)) ?> min</span></td>
        <td role="cell" data-label="Status"><span class="badge <?= e(status_badge_class($trip['status'])) ?>"><?= e($trip['status']) ?></span></td>
        <td role="cell" class="record-actions text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('trip_detail', ['id' => $trip['id']])) ?>">Details <i class="bi bi-chevron-right" aria-hidden="true"></i></a></td>
    </tr><?php endforeach; ?>
    <?php if (!$listing['rows']): ?><tr role="row"><td role="cell" colspan="7" class="text-center text-secondary py-5">No trips match these filters.</td></tr><?php endif; ?>
    </tbody></table></div>
</div></div>
<?php if ($listing['pages'] > 1): ?>
<nav aria-label="Trip pages" class="mt-3"><ul class="pagination justify-content-end">
    <?php $query = ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'status' => $filters['status'], 'truck_id' => $filters['truck_id']]; ?>
    <li class="page-item <?= $listing['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url('trips', $query + ['p' => max(1, $listing['page'] - 1)])) ?>">Previous</a></li>
    <li class="page-item disabled"><span class="page-link">Page <?= (int) $listing['page'] ?> of <?= (int) $listing['pages'] ?></span></li>
    <li class="page-item <?= $listing['page'] >= $listing['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url('trips', $query + ['p' => min($listing['pages'], $listing['page'] + 1)])) ?>">Next</a></li>
</ul></nav>
<?php endif; ?>
