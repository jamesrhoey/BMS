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
                    <th style="width: 6%; min-width: 50px;">ID</th>
                    <th style="width: 14%; min-width: 120px;">Timestamp &darr;</th>
                    <th style="width: 15%; min-width: 130px;">Actor</th>
                    <th style="width: 16%; min-width: 130px;">Action</th>
                    <th style="width: 12%; min-width: 100px;">Entity</th>
                    <th style="width: 27%; min-width: 200px;">Description</th>
                    <th style="width: 10%; min-width: 90px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4 small">No audit log entries match the selected criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-secondary small">#<?= (int) $log['id'] ?></td>
                            <td class="small text-nowrap"><?= format_datetime($log['created_at']) ?></td>
                            <td class="small">
                                <?php if (!empty($log['username'])): ?>
                                    <div class="fw-semibold text-truncate"><?= e($log['full_name']) ?></div>
                                    <div class="text-secondary font-monospace text-truncate">@<?= e($log['username']) ?></div>
                                <?php else: ?>
                                    <span class="text-secondary">System / Guest</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-soft-secondary font-monospace"><?= e($log['action']) ?></span></td>
                            <td class="small text-secondary">
                                <div class="text-truncate"><?= e($log['entity_type']) ?><?= $log['entity_id'] !== null ? ' #' . (int) $log['entity_id'] : '' ?></div>
                            </td>
                            <td class="small" title="<?= e($log['description']) ?>">
                                <div class="text-truncate" style="max-width: 320px;"><?= e($log['description']) ?></div>
                            </td>
                            <td class="small font-monospace text-secondary">
                                <div class="text-nowrap"><?= e(format_ip($log['ip_address'] ?? null)) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_table_footer($currPage, $perPage, count($logs), $totalLogs, $buildLogPageUrl) ?>
</div>
