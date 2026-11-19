<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Route Forecast') ?> · Route Forecast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <?php if (!empty($mapAssets)): ?>
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
    <?php endif; ?>
    <link href="<?= e(asset('css/app.css?v=3d1')) ?>" rel="stylesheet">
</head>
<body>
<?php if (current_user()): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
    <div class="container-xl">
        <a class="navbar-brand fw-bold" href="<?= e(url('dashboard')) ?>">Route Forecast<span class="brand-mark">●</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav me-auto">
                <a class="nav-link <?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>">Dashboard</a>
                <a class="nav-link <?= ($page ?? '') === 'forecast' ? 'active' : '' ?>" href="<?= e(url('forecast')) ?>">Route Forecast</a>
                <a class="nav-link <?= in_array($page ?? '', ['trips', 'trip_detail', 'trip_sheet'], true) ? 'active' : '' ?>" href="<?= e(url('trips')) ?>">Trips</a>
                <a class="nav-link <?= in_array($page ?? '', ['branches', 'branch_form'], true) ? 'active' : '' ?>" href="<?= e(url('branches')) ?>">Branches</a>
                <a class="nav-link <?= ($page ?? '') === 'branch_map' ? 'active' : '' ?>" href="<?= e(url('branch_map')) ?>">Branch map</a>
                <a class="nav-link <?= in_array($page ?? '', ['trucks', 'truck_form'], true) ? 'active' : '' ?>" href="<?= e(url('trucks')) ?>">Trucks</a>
                <a class="nav-link <?= ($page ?? '') === 'load_planner' ? 'active' : '' ?>" href="<?= e(url('load_planner')) ?>">Load planner</a>
                <a class="nav-link <?= in_array($page ?? '', ['boxes', 'box_form'], true) ? 'active' : '' ?>" href="<?= e(url('boxes')) ?>">Box types</a>
            </div>
            <span class="navbar-text me-3 small"><?= e(current_user()['full_name']) ?> <span class="role-pill"><?= e(current_user()['role']) ?></span></span>
            <form action="<?= e(url('logout')) ?>" method="post" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-light" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-xl py-4 py-lg-5">
<?php if ($flashMessage = take_flash()): ?>
    <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
