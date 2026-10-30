<?php
$code = $errorCode ?? '403';
$heading = $errorHeading ?? '403 — Access Denied';
$message = $errorMessage ?? 'You do not have permission to view this page or perform the requested operation.';
?>
<div class="<?= empty($currentUser) ? 'bms-auth-card p-4 p-md-5 text-center' : 'bms-card p-4 p-md-5 text-center my-4 mx-auto' ?>" style="max-width: 600px;">
    <div class="display-5 fw-bold text-danger mb-2"><?= e($code) ?></div>
    <h2 class="h4 fw-semibold mb-3"><?= e($heading) ?></h2>
    <p class="text-secondary mb-4"><?= e($message) ?></p>
    <div class="d-flex justify-content-center gap-2">
        <?php if (!empty($currentUser)): ?>
            <a href="<?= url('/dashboard') ?>" class="btn btn-primary">Return to Dashboard</a>
        <?php else: ?>
            <a href="<?= url('/login') ?>" class="btn btn-primary">Go to Sign In</a>
        <?php endif; ?>
    </div>
</div>

