<?php

use App\Core\Session;

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
$flashWarning = Session::getFlash('warning');
$validationErrors = Session::errors();
?>

<?php if (is_string($flashSuccess) && $flashSuccess !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <?= e($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (is_string($flashWarning) && $flashWarning !== ''): ?>
    <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
        <?= e($flashWarning) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (is_string($flashError) && $flashError !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <?= e($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($validationErrors !== []): ?>
    <div class="alert alert-danger shadow-sm" role="alert">
        <div class="fw-semibold mb-1">Please correct the following errors:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($validationErrors as $errMsg): ?>
                <li><?= e($errMsg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

