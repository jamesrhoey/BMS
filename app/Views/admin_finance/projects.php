<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$totalRows = (int) ($pagination['total'] ?? 0);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['sort'] ?? 'forwarded') !== 'forwarded';

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'sort' => ($filters['sort'] ?? 'forwarded') !== 'forwarded' ? $filters['sort'] : '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/admin-finance/projects' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/admin-finance/projects') ?>" class="bms-table-toolbar">
        <h3 class="h6 fw-bold mb-0 me-auto">Admin Finance Incoming Projects &amp; JOM Issuance Registry</h3>

        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by Admin Finance status">
            <option value="">All Incoming Projects</option>
            <option value="awaiting_jom" <?= ($filters['status'] ?? '') === 'awaiting_jom' ? 'selected' : '' ?>>Awaiting JOM Issuance</option>
            <option value="under_review" <?= ($filters['status'] ?? '') === 'under_review' ? 'selected' : '' ?>>Under Admin Finance Review</option>
            <option value="jom_issued" <?= ($filters['status'] ?? '') === 'jom_issued' ? 'selected' : '' ?>>JOM Issued</option>
            <option value="forwarded_to_operations" <?= ($filters['status'] ?? '') === 'forwarded_to_operations' ? 'selected' : '' ?>>Forwarded to Operations</option>
        </select>

        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort by date">
            <option value="forwarded" <?= ($filters['sort'] ?? 'forwarded') === 'forwarded' ? 'selected' : '' ?>>PM Forwarded Date &darr;</option>
            <option value="approved" <?= ($filters['sort'] ?? '') === 'approved' ? 'selected' : '' ?>>PM Approval Date &darr;</option>
            <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="client" <?= ($filters['sort'] ?? '') === 'client' ? 'selected' : '' ?>>Client Name</option>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search project, client, JOM...">

        <button type="submit" class="bms-toolbar-icon-btn" title="Apply Filters" aria-label="Apply Filters">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/admin-finance/projects') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Client / End User</th>
                    <th>Project Identifier</th>
                    <th>PM Approval Date</th>
                    <th>JOM Status</th>
                    <th>Assigned JOM Number</th>
                    <th>Submitted By</th>
                    <th class="text-end">Available Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4 small">
                            No projects found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <?php
                        $pId = (int) $proj['id'];
                        $pStatus = (string) ($proj['status'] ?? '');
                        $hasJom = !empty($proj['jom_number']);
                        $isForwardedOps = !empty($proj['forwarded_to_ops_at']) || $pStatus === 'Forwarded to Operations';
                        $canStartAfinReview = !$hasJom && !$isForwardedOps && $pStatus === 'Forwarded to Admin Finance';
                        $canIssueJom = !$hasJom && !$isForwardedOps;
                        $canForwardOps = $hasJom && !$isForwardedOps;

                        if ($isForwardedOps) {
                            $jomStatusLabel = 'Forwarded to Operations';
                            $jomStatusBadge = 'badge-soft-info';
                        } elseif ($hasJom) {
                            $jomStatusLabel = 'JOM Issued';
                            $jomStatusBadge = 'badge-soft-success';
                        } elseif ($pStatus === 'Under Admin Finance Review') {
                            $jomStatusLabel = 'Under Admin Finance Review';
                            $jomStatusBadge = 'badge-soft-warning';
                        } else {
                            $jomStatusLabel = 'Awaiting JOM Issuance';
                            $jomStatusBadge = 'badge-soft-primary';
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('/projects/' . $pId) ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($proj['project_name']) ?>
                                </a>
                                <div class="mt-1">
                                    <span class="badge <?= project_status_badge_class($pStatus) ?>"><?= e($pStatus) ?></span>
                                </div>
                            </td>
                            <td><?= e($proj['client_name']) ?></td>
                            <td>
                                <span class="font-monospace small fw-semibold"><?= e($proj['project_code']) ?></span>
                            </td>
                            <td class="small text-nowrap">
                                <?= format_datetime($proj['verified_at'] ?? $proj['forwarded_to_afin_at'] ?? null) ?>
                                <?php if (!empty($proj['verifier_name'])): ?>
                                    <div class="text-secondary" style="font-size: 0.75rem;">by <?= e($proj['verifier_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $jomStatusBadge ?>"><?= e($jomStatusLabel) ?></span>
                            </td>
                            <td>
                                <?php if ($hasJom): ?>
                                    <span class="badge bg-primary font-monospace"><?= e($proj['jom_number']) ?></span>
                                    <?php if (!empty($proj['jom_issued_at'])): ?>
                                        <div class="text-secondary" style="font-size: 0.72rem;"><?= format_datetime($proj['jom_issued_at']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-secondary small">Pending Issuance</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if (!empty($proj['submitter_name'])): ?>
                                    <?= e($proj['submitter_name']) ?>
                                <?php else: ?>
                                    <span class="text-secondary"><?= e($proj['creator_name'] ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="bms-action-group">
                                    <a href="<?= url('/projects/' . $pId) ?>"
                                       class="bms-action-icon bms-action-icon--primary"
                                       title="Open Project & Admin Finance Panel"
                                       aria-label="Open Project & Admin Finance Panel">
                                        <?= bms_icon('eye') ?>
                                    </a>

                                    <?php if ($canStartAfinReview): ?>
                                        <form method="POST" action="<?= url('/projects/' . $pId . '/admin-finance-review') ?>" class="m-0 d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--primary"
                                                    title="Mark Under Admin Finance Review"
                                                    aria-label="Mark Under Admin Finance Review">
                                                <?= bms_icon('prepare') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($canIssueJom): ?>
                                        <form method="POST"
                                              action="<?= url('/projects/' . $pId . '/issue-jom') ?>"
                                              class="m-0 d-inline"
                                              data-confirm="Confirm official JOM Number issuance for approved project '<?= e($proj['project_code']) ?>' (<?= e($proj['project_name']) ?>)? This action is permanent and assigns a unique JOM number.">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--success"
                                                    title="Issue JOM Number"
                                                    aria-label="Issue JOM Number">
                                                <?= bms_icon('check') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($canForwardOps): ?>
                                        <form method="POST"
                                              action="<?= url('/projects/' . $pId . '/forward-to-operations') ?>"
                                              class="m-0 d-inline"
                                              data-confirm="Forward JOM Number '<?= e($proj['jom_number']) ?>', SLD, and Marketing BOM for project '<?= e($proj['project_code']) ?>' to Operations?">
                                            <?= csrf_field() ?>
                                            <button type="submit"
                                                    class="bms-action-icon bms-action-icon--primary"
                                                    title="Forward JOM, SLD & Marketing BOM to Operations"
                                                    aria-label="Forward JOM, SLD & Marketing BOM to Operations">
                                                <?= bms_icon('send') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer($currentPage, $perPage, count($projects), $totalRows, $buildPageUrl) ?>
</div>
