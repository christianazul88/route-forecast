<?php
$metrics = [
    ["Today's trips", (int) $summary['today_count'], 'calendar2-week', 'indigo'],
    ['Planned', (int) $summary['counts']['Planned'], 'clipboard-check', 'violet'],
    ['Dispatched', (int) $summary['counts']['Dispatched'], 'truck', 'azure'],
    ['Completed', (int) $summary['counts']['Completed'], 'check-circle', 'mint'],
    ['Cancelled', (int) $summary['counts']['Cancelled'], 'x-circle', 'rose'],
];
?>
<section class="dashboard-hero page-heading mb-4" aria-labelledby="dashboard-title">
    <div class="dashboard-hero-content">
        <p class="dashboard-date"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(date('l, F j, Y', strtotime($today))) ?></p>
        <h1 class="fw-bold mb-2" id="dashboard-title">Dashboard</h1>
        <p class="mb-4">Review trips and prepare your next delivery.</p>
        <a class="btn dashboard-primary" href="<?= e(url('forecast')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Plan a route</a>
    </div>
    <div class="hero-route" aria-hidden="true"><span></span><span></span><span></span><i class="bi bi-truck"></i></div>
</section>
<section class="dashboard-metrics mb-4" aria-label="Trip counts">
    <?php foreach ($metrics as [$label, $count, $icon, $color]): ?>
        <div class="dashboard-metric dashboard-metric-<?= e($color) ?>">
            <span class="dashboard-metric-icon"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></span>
            <span class="dashboard-metric-label"><?= e($label) ?></span>
            <strong class="dashboard-metric-value"><?= $count ?></strong>
        </div>
    <?php endforeach; ?>
</section>
<nav class="dashboard-tools mb-4" aria-label="Planning shortcuts">
    <?php foreach ([['load_planner', 'Plan a load', 'boxes', 'violet'], ['fuel', 'Estimate fuel', 'fuel-pump', 'mint'], ['branch_map', 'View branch map', 'geo-alt', 'azure']] as [$route, $label, $icon, $color]): ?>
        <a href="<?= e(url($route)) ?>"><span class="dashboard-metric-icon dashboard-metric-<?= e($color) ?>"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></span><span><?= e($label) ?></span><i class="bi bi-arrow-right ms-auto" aria-hidden="true"></i></a>
    <?php endforeach; ?>
</nav>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Today's trips</h2><a class="app-inline-action" href="<?= e(url('trips', ['date_from' => $today, 'date_to' => $today])) ?>" aria-label="View all of today's trips">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            <?php if (!$summary['today']): ?><p class="app-empty mb-0"><i class="bi bi-calendar2-check" aria-hidden="true"></i> No trips scheduled for today.</p><?php endif; ?>
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
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Next 7 days</h2><a class="app-inline-action" href="<?= e(url('trips', ['date_from' => date('Y-m-d', strtotime('+1 day')), 'date_to' => $lastUpcomingDate])) ?>" aria-label="View all trips in the next 7 days">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            <?php if (!$summary['upcoming']): ?><p class="app-empty mb-0"><i class="bi bi-calendar2-week" aria-hidden="true"></i> No upcoming planned or dispatched trips.</p><?php endif; ?>
            <?php foreach ($summary['upcoming'] as $trip): ?>
            <a class="dashboard-trip" href="<?= e(url('trip_detail', ['id' => $trip['id']])) ?>">
                <div><strong><?= e($trip['trip_no']) ?></strong><div class="small text-secondary"><?= e($trip['origin_name']) ?> → <?= e($trip['destination_name']) ?></div><div class="small text-secondary"><?= e(date('M j', strtotime($trip['trip_date']))) ?> · <?= e($trip['plate_no']) ?></div></div>
                <span class="badge <?= e(status_badge_class($trip['status'])) ?>"><?= e($trip['status']) ?></span>
            </a>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>
