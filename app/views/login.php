<div class="login-shell">
    <div class="login-card card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="eyebrow mb-2">LOGISTICS PLANNING</div>
            <h1 class="h2 fw-bold mb-1">Welcome back</h1>
            <p class="text-secondary mb-4">Sign in to manage branches and trucks.</p>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input id="username" name="username" type="text" class="form-control form-control-lg" autocomplete="username" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
            </form>
        </div>
    </div>
    <p class="text-center text-secondary small mt-3">For authorized company staff only.</p>
</div>
