<?php
$currPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 5);
$totalLogs = (int) ($pagination['total'] ?? 0);
$hasLogFilters = ($filters['q'] ?? '') !== '' || ($filters['entity_type'] ?? '') !== '';

$buildLogPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'] ?? '',
        'entity_type' => $filters['entity_type'] ?? '',
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn($v): bool => $v !== '');

    return url('/activity-logs' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<div class="bms-table-panel">
    <form method="GET" action="<?= url('/activity-logs') ?>" class="bms-table-toolbar">
        <select id="entity_type" name="entity_type" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by entity type">
            <option value="">All Entities</option>
            <?php foreach (['user', 'project', 'document', 'auth', 'security', 'route', 'authorization'] as $type): ?>
                <option value="<?= e($type) ?>" <?= ($filters['entity_type'] ?? '') === $type ? 'selected' : '' ?>>
                    <?= e(ucfirst($type)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text"
               id="q"
               name="q"
               value="<?= e($filters['q'] ?? '') ?>"
               class="form-control form-control-sm bms-toolbar-input"
               placeholder="Search 'Keyword'">

        <button type="submit" class="bms-toolbar-icon-btn" title="Filter Logs" aria-label="Filter Logs">
            <?= bms_icon('search', 16) ?>
        </button>

        <?php if ($hasLogFilters): ?>
            <a href="<?= url('/activity-logs') ?>" class="bms-toolbar-icon-btn bms-toolbar-icon-btn--outline" title="Clear Filters" aria-label="Clear Filters">
                <?= bms_icon('reset', 16) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Timestamp &darr;</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">No audit log entries match the selected criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-secondary small">#<?= (int) $log['id'] ?></td>
                            <td class="small text-nowrap"><?= format_datetime($log['created_at']) ?></td>
                            <td class="small">
                                <?php if (!empty($log['username'])): ?>
                                    <div class="fw-semibold"><?= e($log['full_name']) ?></div>
                                    <div class="text-secondary font-monospace">@<?= e($log['username']) ?></div>
                                <?php else: ?>
                                    <span class="text-secondary">System / Guest</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-soft-secondary font-monospace"><?= e($log['action']) ?></span></td>
                            <td class="small text-secondary">
                                <?= e($log['entity_type']) ?>
                                <?php if ($log['entity_id'] !== null): ?>
                                    #<?= (int) $log['entity_id'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e($log['description']) ?></td>
                            <td class="small font-monospace text-secondary"><?= e($log['ip_address'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer($currPage, $perPage, count($logs), $totalLogs, $buildLogPageUrl) ?>
</div>
