<?php
$heading = $errorHeading ?? '404 — Page Not Found';
$message = $errorMessage ?? 'The page or resource you requested does not exist or may have been moved.';
?>
<div class="<?= empty($currentUser) ? 'bms-auth-card p-4 p-md-5 text-center' : 'bms-card p-4 p-md-5 text-center my-4 mx-auto' ?>" style="max-width: 600px;">
    <div class="display-5 fw-bold text-secondary mb-2">404</div>
    <h2 class="h4 fw-semibold mb-3"><?= e($heading) ?></h2>
    <p class="text-secondary mb-4"><?= e($message) ?></p>
    <div>
        <?php if (!empty($currentUser)): ?>
            <a href="<?= url('/dashboard') ?>" class="btn btn-primary">Return to Dashboard</a>
        <?php else: ?>
            <a href="<?= url('/login') ?>" class="btn btn-primary">Go to Sign In</a>
        <?php endif; ?>
    </div>
</div>

