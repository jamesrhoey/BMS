<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 15);
$totalRows = (int) ($pagination['total'] ?? 0);
$currentCategory = strtolower((string) ($filters['category'] ?? ''));

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter(array_merge($filters, [
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ]), static fn($v): bool => $v !== '');
    return url('/operations/history' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<div class="bms-table-panel">
    <div class="bms-table-toolbar flex-wrap gap-2">
        <h3 class="h6 fw-bold mb-0 me-auto">Operations Project History &amp; Submissions Audit</h3>

        <form method="GET" action="<?= url('/operations/history') ?>" class="d-flex align-items-center gap-2">
            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <option value="mrc" <?= $currentCategory === 'mrc' ? 'selected' : '' ?>>MRC Projects</option>
                <option value="special_project" <?= $currentCategory === 'special_project' ? 'selected' : '' ?>>Special Projects</option>
            </select>
            <div class="input-group input-group-sm">
                <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control" placeholder="Search history...">
                <button type="submit" class="btn btn-outline-secondary">
                    <?= bms_icon('search', 14) ?>
                </button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="min-width: 140px;">Project Code</th>
                    <th scope="col" style="min-width: 180px;">Project Name</th>
                    <th scope="col">Client</th>
                    <th scope="col" class="text-center" style="width: 110px;">Category</th>
                    <th scope="col">Received Date</th>
                    <th scope="col">OPS Finance Submitted</th>
                    <th scope="col">Submitted By</th>
                    <th scope="col" class="text-center">Status</th>
                    <th scope="col" class="text-end" style="width: 90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-5">
                            <div class="mb-2"><?= bms_icon('history', 32) ?></div>
                            <strong>No project history records found.</strong>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $item): ?>
                        <?php
                        $isSpecial = ($item['category_label'] ?? '') === 'Special Project';
                        $receivedDate = !empty($item['forwarded_to_ops_at']) ? date('M d, Y', strtotime((string) $item['forwarded_to_ops_at'])) : '—';
                        $submittedDate = !empty($item['submitted_to_opfin_at']) ? date('M d, Y H:i', strtotime((string) $item['submitted_to_opfin_at'])) : '—';
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('/operations/projects/' . $item['id']) ?>" class="fw-semibold text-primary text-decoration-none">
                                    <?= e($item['project_code']) ?>
                                </a>
                                <?php if (!empty($item['jom_number'])): ?>
                                    <div class="small text-muted font-monospace"><?= e($item['jom_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-medium text-dark text-truncate" style="max-width: 220px;" title="<?= e($item['project_name']) ?>">
                                    <?= e($item['project_name']) ?>
                                </div>
                            </td>
                            <td class="small text-dark"><?= e($item['client_name']) ?></td>
                            <td class="text-center">
                                <?php if ($isSpecial): ?>
                                    <span class="badge bg-purple text-white" style="background-color: #6f42c1;">Special Project</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">MRC</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?= e($receivedDate) ?></td>
                            <td class="small text-secondary"><?= e($submittedDate) ?></td>
                            <td class="small text-secondary"><?= e($item['submitted_to_opfin_by_name'] ?? '—') ?></td>
                            <td class="text-center">
                                <?php if (!empty($item['is_submitted_to_opfin'])): ?>
                                    <span class="badge bg-dark">Submitted to OPS Finance</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e($item['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= url('/operations/projects/' . $item['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalRows > $perPage): ?>
        <div class="bms-table-pagination d-flex align-items-center justify-content-between p-3 border-top">
            <div class="text-secondary small">
                Showing <?= number_format(min($totalRows, ($currentPage - 1) * $perPage + 1)) ?> to <?= number_format(min($totalRows, $currentPage * $perPage)) ?> of <?= number_format($totalRows) ?> records
            </div>
            <nav aria-label="History pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildPageUrl($currentPage - 1) ?>" aria-label="Previous">&laquo;</a>
                    </li>
                    <?php
                    $totalPages = (int) ($pagination['total_pages'] ?? 1);
                    for ($p = 1; $p <= $totalPages; $p++):
                        if ($p === 1 || $p === $totalPages || ($p >= $currentPage - 1 && $p <= $currentPage + 1)):
                    ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($p) ?>"><?= $p ?></a>
                        </li>
                    <?php elseif ($p === $currentPage - 2 || $p === $currentPage + 2): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; endfor; ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildPageUrl($currentPage + 1) ?>" aria-label="Next">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
