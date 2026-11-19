<div class="login-shell"><div class="guest-brand"><span class="app-brand-icon"><i class="bi bi-signpost-split-fill" aria-hidden="true"></i></span>Route Forecast</div><div class="login-card card border-0 shadow-sm"><div class="card-body p-4 p-md-5">
    <h1 class="h2 fw-bold mb-1">Create the first admin</h1>
    <p class="text-secondary mb-4">Use the setup token from your server configuration. This page closes after the first account is created.</p>
    <?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="setup.php">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="setup_token">Setup token</label><input id="setup_token" name="setup_token" type="password" class="form-control" autocomplete="off" required></div>
        <div class="mb-3"><label class="form-label" for="full_name">Full name</label><input id="full_name" name="full_name" class="form-control" value="<?= e($values['full_name']) ?>" maxlength="120" autocomplete="name" required></div>
        <div class="mb-3"><label class="form-label" for="username">Username</label><input id="username" name="username" class="form-control" value="<?= e($values['username']) ?>" maxlength="80" autocomplete="username" autocapitalize="none" spellcheck="false" required></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label><input id="password" name="password" type="password" class="form-control" minlength="12" autocomplete="new-password" aria-describedby="setupPasswordHelp" required><div id="setupPasswordHelp" class="form-text">Use at least 12 characters.</div></div>
        <div class="mb-4"><label class="form-label" for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" class="form-control" minlength="12" autocomplete="new-password" required></div>
        <button class="btn btn-primary w-100" type="submit">Create admin</button>
    </form>
</div></div></div>
