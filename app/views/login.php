<div class="login-shell">
    <div class="guest-brand"><span class="app-brand-icon"><i class="bi bi-signpost-split-fill" aria-hidden="true"></i></span>Route Forecast</div>
    <div class="login-card card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h2 fw-bold mb-2">Sign in</h1>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input id="username" name="username" type="text" class="form-control form-control-lg" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required>
                        <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Show password" title="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
            </form>
        </div>
    </div>
    <p class="text-center text-secondary small mt-3">For authorized company staff only.</p>
</div>
