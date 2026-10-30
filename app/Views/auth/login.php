<div class="bms-auth-card">
    <div class="p-4 p-md-5 pb-4 text-center bms-auth-header">
        <img src="<?= asset('images/pagecom-logo.png') ?>" alt="PAGECOM INC." class="bms-auth-logo mb-3">
        <h1 class="h6 fw-bold mb-1 text-dark text-uppercase" style="letter-spacing: 0.06em;">Business Management System</h1>
        
    </div>

    <div class="p-4 p-md-5 pt-4">
        <h2 class="h6 fw-semibold text-uppercase text-secondary mb-3" style="letter-spacing: 0.05em;">Account Sign In</h2>

        <?php if (!empty($loggedOut)): ?>
            <div class="alert alert-info py-2 small" role="alert">
                You have been securely signed out.
            </div>
        <?php endif; ?>

        <?php require BASE_PATH . '/app/Views/layouts/partials/alerts.php'; ?>

        <form method="POST" action="<?= url('/login') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label fw-medium">Username</label>
                <input
                    type="text"
                    class="form-control <?= has_error('username') ? 'is-invalid' : '' ?>"
                    id="username"
                    name="username"
                    value="<?= old('username') ?>"
                    maxlength="50"
                    autocomplete="username"
                    required
                    autofocus
                >
                <?php if (has_error('username')): ?>
                    <div class="invalid-feedback"><?= field_error('username') ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-medium">Password</label>
                <input
                    type="password"
                    class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>"
                    id="password"
                    name="password"
                    maxlength="255"
                    autocomplete="current-password"
                    required
                >
                <?php if (has_error('password')): ?>
                    <div class="invalid-feedback"><?= field_error('password') ?></div>
                <?php endif; ?>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">Sign In</button>
            </div>
        </form>

       
    </div>
</div>

