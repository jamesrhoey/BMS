<?php

use App\Core\Session;

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
$flashWarning = Session::getFlash('warning');
$validationErrors = Session::errors();
$hasLoggedOutNotice = !empty($loggedOut);

$hasAnyToast = (is_string($flashSuccess) && $flashSuccess !== '')
    || (is_string($flashWarning) && $flashWarning !== '')
    || (is_string($flashError) && $flashError !== '')
    || $validationErrors !== []
    || $hasLoggedOutNotice;
?>

<?php if ($hasAnyToast): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3 bms-toast-container" id="bmsToastContainer">
        <?php if ($hasLoggedOutNotice): ?>
            <div class="toast bms-toast bms-toast--info fade show" role="status" aria-live="polite" aria-atomic="true" data-bs-autohide="true" data-bs-delay="4500">
                <div class="toast-header">
                    <span class="bms-toast-dot bg-dark me-2" aria-hidden="true"></span>
                    <strong class="me-auto text-dark">Signed Out</strong>
                    <small class="text-secondary">Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    You have been securely signed out.
                </div>
            </div>
        <?php endif; ?>

        <?php if (is_string($flashSuccess) && $flashSuccess !== ''): ?>
            <div class="toast bms-toast bms-toast--success fade show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
                <div class="toast-header">
                    <span class="bms-toast-dot bg-success me-2" aria-hidden="true"></span>
                    <strong class="me-auto text-success">Success</strong>
                    <small class="text-secondary">Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= e($flashSuccess) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (is_string($flashWarning) && $flashWarning !== ''): ?>
            <div class="toast bms-toast bms-toast--warning fade show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="6000">
                <div class="toast-header">
                    <span class="bms-toast-dot bg-warning me-2" aria-hidden="true"></span>
                    <strong class="me-auto text-warning-emphasis">Warning</strong>
                    <small class="text-secondary">Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= e($flashWarning) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (is_string($flashError) && $flashError !== ''): ?>
            <div class="toast bms-toast bms-toast--danger fade show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="7000">
                <div class="toast-header">
                    <span class="bms-toast-dot bg-danger me-2" aria-hidden="true"></span>
                    <strong class="me-auto text-danger">Error</strong>
                    <small class="text-secondary">Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= e($flashError) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($validationErrors !== []): ?>
            <div class="toast bms-toast bms-toast--danger fade show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="8000">
                <div class="toast-header">
                    <span class="bms-toast-dot bg-danger me-2" aria-hidden="true"></span>
                    <strong class="me-auto text-danger">Validation Error</strong>
                    <small class="text-secondary">Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <div class="fw-semibold mb-1">Please correct the following errors:</div>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($validationErrors as $errMsg): ?>
                            <li><?= e($errMsg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>


