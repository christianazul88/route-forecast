<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><div class="eyebrow mb-2">LOGISTICS OVERVIEW</div><h1 class="display-6 fw-bold mb-1">Dashboard</h1><p class="text-secondary mb-0"><?= e(date('l, F j, Y', strtotime($today))) ?> · Trip activity at a glance.</p></div>
    <a class="btn btn-primary" href="<?= e(url('forecast')) ?>">+ Plan a route</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm metric-card"><div class="card-body p-4"><div class="text-secondary small">Today's trips</div><div class="metric-value"><?= (int) $summary['today_count'] ?></div></div></div></div>
    <?php foreach (Trip::STATUSES as $status): ?>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm metric-card"><div class="card-body p-4"><div class="text-secondary small"><?= e($status) ?></div><div class="metric-value"><?= (int) $summary['counts'][$status] ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Today's trips</h2><a class="small text-decoration-none" href="<?= e(url('trips', ['date_from' => $today, 'date_to' => $today])) ?>">View all</a></div>
            <?php if (!$summary['today']): ?><p class="text-secondary mb-0 py-3">No trips scheduled for today.</p><?php endif; ?>
            <?php foreach ($summary['today'] as $trip): ?>
            <a class="dashboard-trip" href="<?= e(url('trip_detail', ['id' => $trip['id']])) ?>">
                <div><strong><?= e($trip['trip_no']) ?></strong><div class="small text-secondary"><?= e($trip['origin_name']) ?> → <?= e($trip['destination_name']) ?></div><div class="small text-secondary"><?= e($trip['plate_no']) ?> · <?= e((string) $trip['total_distance_km']) ?> km</div></div>
                <span class="badge <?= e(status_badge_class($trip['status'])) ?>"><?= e($trip['status']) ?></span>
            </a>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Upcoming 7 days</h2><a class="small text-decoration-none" href="<?= e(url('trips', ['date_from' => date('Y-m-d', strtotime('+1 day')), 'date_to' => $lastUpcomingDate])) ?>">View all</a></div>
            <?php if (!$summary['upcoming']): ?><p class="text-secondary mb-0 py-3">No upcoming planned or dispatched trips.</p><?php endif; ?>
            <?php foreach ($summary['upcoming'] as $trip): ?>
            <a class="dashboard-trip" href="<?= e(url('trip_detail', ['id' => $trip['id']])) ?>">
                <div><strong><?= e($trip['trip_no']) ?></strong><div class="small text-secondary"><?= e($trip['origin_name']) ?> → <?= e($trip['destination_name']) ?></div><div class="small text-secondary"><?= e(date('M j', strtotime($trip['trip_date']))) ?> · <?= e($trip['plate_no']) ?></div></div>
                <span class="badge <?= e(status_badge_class($trip['status'])) ?>"><?= e($trip['status']) ?></span>
            </a>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>
