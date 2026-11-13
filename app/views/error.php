<div class="card border-0 shadow-sm mx-auto" style="max-width: 620px">
    <div class="card-body p-4 p-lg-5">
        <div class="eyebrow mb-2">ROUTE FORECAST</div>
        <h1 class="h3">Something went wrong</h1>
        <p class="text-secondary mb-4"><?= e($message) ?></p>
        <a class="btn btn-primary" href="<?= e(url(current_user() ? 'branches' : 'login')) ?>">Go back</a>
    </div>
</div>
