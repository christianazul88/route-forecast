<?php
$viewer = current_user();
$navigation = [
    'Planning' => [
        ['dashboard', 'Dashboard', 'grid-1x2', []],
        ['forecast', 'Route Forecast', 'signpost-split', []],
        ['trips', 'Trips', 'calendar2-week', ['trip_detail', 'trip_sheet']],
        ['load_planner', 'Load planner', 'boxes', []],
        ['fuel', 'Fuel planner', 'fuel-pump', []],
    ],
    'Master data' => [
        ['branches', 'Branches', 'geo-alt', ['branch_form', 'branch_save']],
        ['branch_map', 'Branch map', 'map', []],
        ['trucks', 'Trucks', 'truck', ['truck_form', 'truck_save']],
        ['boxes', 'Box types', 'box-seam', ['box_form', 'box_save']],
    ],
];
if (($viewer['role'] ?? null) === 'Admin') {
    $navigation['Administration'] = [['audit_log', 'Audit log', 'journal-text', []]];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#171735">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Route Forecast') ?> · Route Forecast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if (!empty($mapAssets)): ?>
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
    <?php endif; ?>
    <link href="<?= e(asset('css/app.css?v=mobile1')) ?>" rel="stylesheet">
</head>
<body class="<?= $viewer ? 'app-body' : 'guest-body' ?>">
<a class="app-skip visually-hidden-focusable" href="#main-content">Skip to content</a>
<?php if ($viewer): ?>
<header class="app-topbar d-xl-none">
    <a class="app-brand" href="<?= e(url('dashboard')) ?>"><span class="app-brand-icon"><i class="bi bi-signpost-split-fill" aria-hidden="true"></i></span>Route Forecast</a>
    <button class="app-menu-button btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#appMenu" aria-controls="appMenu"><i class="bi bi-list" aria-hidden="true"></i> Menu</button>
</header>
<div class="app-frame">
    <aside class="app-sidebar offcanvas-xl offcanvas-start" id="appMenu" tabindex="-1" aria-labelledby="appMenuTitle">
        <div class="app-sidebar-heading">
            <a class="app-brand" id="appMenuTitle" href="<?= e(url('dashboard')) ?>"><span class="app-brand-icon"><i class="bi bi-signpost-split-fill" aria-hidden="true"></i></span>Route Forecast</a>
            <button type="button" class="btn-close btn-close-white d-xl-none" data-bs-dismiss="offcanvas" data-bs-target="#appMenu" aria-label="Close menu"></button>
        </div>
        <nav class="app-menu" aria-label="Main navigation">
            <?php foreach ($navigation as $group => $items): ?>
                <div class="app-menu-group"><p><?= e($group) ?></p>
                    <?php foreach ($items as [$route, $label, $icon, $related]): $active = $page === $route || in_array($page, $related, true); ?>
                        <a class="app-nav-link<?= $active ? ' active' : '' ?>" href="<?= e(url($route)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="app-account">
            <div class="app-account-name"><strong><?= e($viewer['full_name']) ?></strong><span><?= e($viewer['role']) ?></span></div>
            <form action="<?= e(url('logout')) ?>" method="post" class="m-0">
                <?= csrf_field() ?>
                <button class="btn app-signout" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out</button>
            </form>
        </div>
    </aside>
    <div class="app-workspace">
<?php endif; ?>
<main id="main-content" class="container-xl app-main" tabindex="-1"<?= $viewer ? ' data-audit-url="' . e(url('audit_event')) . '"' : '' ?>>
<?php if ($flashMessage = take_flash()): ?>
    <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
