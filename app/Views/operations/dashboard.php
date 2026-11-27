<?php
$currentPage = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$totalRows = (int) ($pagination['total'] ?? 0);
$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['category'] ?? '') !== ''
    || ($filters['sort'] ?? 'received') !== 'received'
    || ($filters['date_from'] ?? '') !== ''
    || ($filters['date_to'] ?? '') !== '';

$currentCategory = strtolower((string) ($filters['category'] ?? ''));

$buildCategoryUrl = static function (string $cat) use ($filters): string {
    $q = array_filter(array_merge($filters, [
        'category' => $cat,
        'page' => 1,
    ]), static fn($v): bool => $v !== '');
    return url('/operations/dashboard' . ($q !== [] ? '?' . http_build_query($q) : ''));
};

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter(array_merge($filters, [
        'page' => $targetPage > 1 ? (string) $targetPage : '',
    ]), static fn($v): bool => $v !== '');
    return url('/operations/dashboard' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<!-- Category Filter Pills -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="btn-group bms-category-pills" role="group" aria-label="Project Category">
        <a href="<?= $buildCategoryUrl('') ?>" class="btn btn-sm <?= $currentCategory === '' ? 'btn-primary active' : 'btn-outline-secondary' ?>">
            All Projects (<?= (int) ($stats['total_received'] ?? 0) ?>)
        </a>
        <a href="<?= $buildCategoryUrl('mrc') ?>" class="btn btn-sm <?= $currentCategory === 'mrc' ? 'btn-primary active' : 'btn-outline-secondary' ?>">
            <span class="badge bg-light text-dark me-1">MRC</span> Projects (<?= (int) ($stats['mrc_projects'] ?? 0) ?>)
        </a>
        <a href="<?= $buildCategoryUrl('special_project') ?>" class="btn btn-sm <?= $currentCategory === 'special_project' ? 'btn-primary active' : 'btn-outline-secondary' ?>">
            <span class="badge bg-light text-dark me-1">Special</span> Projects (<?= (int) ($stats['special_projects'] ?? 0) ?>)
        </a>
    </div>

    <div class="text-secondary small">
        Showing <?= number_format($totalRows) ?> total project<?= $totalRows === 1 ? '' : 's' ?>
    </div>
</div>

<!-- 8 Official Operations Department KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Total Projects Received</div>
                <div class="bms-stat-value text-primary"><?= (int) ($stats['total_received'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Forwarded by Project Management</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?category=mrc') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">MRC Projects Received</div>
                <div class="bms-stat-value text-info"><?= (int) ($stats['mrc_projects'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Maintenance &amp; Repair Contracts</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?category=special_project') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Special Projects Received</div>
                <div class="bms-stat-value text-indigo" style="color: #6f42c1;"><?= (int) ($stats['special_projects'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Custom &amp; Capital Installations</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?status=incoming') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Pending OPS Processing</div>
                <div class="bms-stat-value text-warning"><?= (int) ($stats['pending_processing'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Newly arrived, untouched</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?status=processing') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Projects in Progress</div>
                <div class="bms-stat-value text-secondary"><?= (int) ($stats['under_processing'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Under OPS processing</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?status=missing_documents') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Missing OPS Documents</div>
                <div class="bms-stat-value text-danger"><?= (int) ($stats['missing_documents'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Need Final SLD / BOM / Request PR</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?status=documents_complete') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Completed OPS Submissions</div>
                <div class="bms-stat-value text-success"><?= (int) ($stats['documents_complete'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Ready for OPS Finance submission</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('/operations/projects?status=submitted_opfin') ?>" class="text-decoration-none">
            <div class="bms-stat-card">
                <div class="bms-stat-label">Submitted to OPS Finance</div>
                <div class="bms-stat-value text-dark"><?= (int) ($stats['submitted_to_opfin'] ?? 0) ?></div>
                <div class="small text-secondary mt-1">Handoff complete (read-only)</div>
            </div>
        </a>
    </div>
</div>

<!-- Projects Table Panel -->
<div class="bms-table-panel">
    <form method="GET" action="<?= url('/operations/dashboard') ?>" class="bms-table-toolbar flex-wrap gap-2">
        <h3 class="h6 fw-bold mb-0 me-auto">Operations Projects Queue</h3>

        <?php if ($currentCategory !== ''): ?>
            <input type="hidden" name="category" value="<?= e($currentCategory) ?>">
        <?php endif; ?>

        <!-- Status Filter -->
        <select id="status" name="status" class="form-select form-select-sm bms-toolbar-select" aria-label="Filter by Status">
            <option value="">All Statuses</option>
            <option value="incoming" <?= ($filters['status'] ?? '') === 'incoming' ? 'selected' : '' ?>>Pending Processing</option>
            <option value="processing" <?= ($filters['status'] ?? '') === 'processing' ? 'selected' : '' ?>>In Progress</option>
            <option value="missing_documents" <?= ($filters['status'] ?? '') === 'missing_documents' ? 'selected' : '' ?>>Missing OPS Documents</option>
            <option value="documents_complete" <?= ($filters['status'] ?? '') === 'documents_complete' ? 'selected' : '' ?>>OPS Documents Complete</option>
            <option value="submitted_opfin" <?= ($filters['status'] ?? '') === 'submitted_opfin' ? 'selected' : '' ?>>Submitted to OPS Finance</option>
        </select>

        <!-- Sorting -->
        <select id="sort" name="sort" class="form-select form-select-sm bms-toolbar-select" aria-label="Sort Order">
            <option value="received" <?= ($filters['sort'] ?? 'received') === 'received' ? 'selected' : '' ?>>Date Received &darr;</option>
            <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="name" <?= ($filters['sort'] ?? '') === 'name' ? 'selected' : '' ?>>Project Name</option>
            <option value="code" <?= ($filters['sort'] ?? '') === 'code' ? 'selected' : '' ?>>Project Code</option>
            <option value="status" <?= ($filters['sort'] ?? '') === 'status' ? 'selected' : '' ?>>Status</option>
        </select>

        <!-- Date Range -->
        <div class="d-flex align-items-center gap-1">
            <input type="date"
                   id="date_from"
                   name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>"
                   class="form-control form-control-sm"
                   style="max-width: 135px;"
                   title="Received Date From">
            <span class="text-secondary small">&ndash;</span>
            <input type="date"
                   id="date_to"
                   name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>"
                   class="form-control form-control-sm"
                   style="max-width: 135px;"
                   title="Received Date To">
        </div>

        <!-- Search Input -->
        <div class="input-group input-group-sm bms-toolbar-search">
            <input type="text"
                   id="q"
                   name="q"
                   value="<?= e($filters['q'] ?? '') ?>"
                   class="form-control"
                   placeholder="Search code, name, client..."
                   aria-label="Search projects">
            <button class="btn btn-outline-secondary" type="submit" title="Search">
                <?= bms_icon('search', 14) ?>
            </button>
        </div>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= url('/operations/dashboard') ?>" class="btn btn-sm btn-link text-decoration-none text-danger" title="Clear Filters">
                Clear
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="min-width: 140px;">Project Code</th>
                    <th scope="col" style="min-width: 180px;">Project Name</th>
                    <th scope="col">Client</th>
                    <th scope="col" class="text-center" style="width: 110px;">Category</th>
                    <th scope="col" style="min-width: 120px;">Received Date</th>
                    <th scope="col" class="text-center" style="min-width: 140px;">Status</th>
                    <th scope="col" class="text-center" style="min-width: 140px;">OPS Documents</th>
                    <th scope="col" class="text-end" style="min-width: 130px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            <div class="mb-2"><?= bms_icon('folder', 32) ?></div>
                            <strong>No projects found in Operations queue.</strong>
                            <div class="small mt-1">Projects will automatically appear here once forwarded by Project Management with JOM and category documents.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $item): ?>
                        <?php
                        $isSpecial = ($item['category_label'] ?? '') === 'Special Project';
                        $receivedDate = !empty($item['forwarded_to_ops_at']) ? date('M d, Y', strtotime((string) $item['forwarded_to_ops_at'])) : '—';
                        $opsDocsCount = (int) ($item['ops_uploaded_count'] ?? 0);
                        $isOpsComplete = !empty($item['is_ops_complete']);
                        $isSubmittedOpfin = !empty($item['is_submitted_to_opfin']);
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
                                <div class="fw-medium text-dark text-truncate" style="max-width: 240px;" title="<?= e($item['project_name']) ?>">
                                    <?= e($item['project_name']) ?>
                                </div>
                                <div class="small text-secondary text-truncate" style="max-width: 240px;">
                                    <?= e($item['project_location'] ?? 'No location') ?>
                                </div>
                            </td>
                            <td class="small">
                                <div class="text-dark fw-medium"><?= e($item['client_name']) ?></div>
                                <?php if (!empty($item['client_contact'])): ?>
                                    <div class="text-muted small"><?= e($item['client_contact']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isSpecial): ?>
                                    Special Project
                                <?php else: ?>
                                    MRC
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary">
                                <?= e($receivedDate) ?>
                                <?php if (!empty($item['forwarded_to_ops_by_name'])): ?>
                                    <div class="text-muted small">by <?= e($item['forwarded_to_ops_by_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isSubmittedOpfin): ?>
                                    <span class="badge bg-dark">Submitted to OPS Finance</span>
                                <?php elseif ($isOpsComplete): ?>
                                    <span class="badge bg-success">OPS Documents Complete</span>
                                <?php elseif ($opsDocsCount > 0): ?>
                                    <span class="badge bg-warning text-dark">Under OPS Processing</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pending Processing</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <!-- Final SLD -->
                                    <span class="badge <?= !empty($item['has_final_sld']) ? 'bg-success' : 'bg-light text-muted border' ?>"
                                          title="<?= !empty($item['has_final_sld']) ? 'Final SLD Uploaded' : 'Final SLD Missing' ?>">
                                        SLD
                                    </span>
                                    <!-- Final BOM -->
                                    <span class="badge <?= !empty($item['has_final_bom']) ? 'bg-success' : 'bg-light text-muted border' ?>"
                                          title="<?= !empty($item['has_final_bom']) ? 'Final BOM Uploaded' : 'Final BOM Missing' ?>">
                                        BOM
                                    </span>
                                    <!-- Request of PR -->
                                    <span class="badge <?= !empty($item['has_request_pr']) ? 'bg-success' : 'bg-light text-muted border' ?>"
                                          title="<?= !empty($item['has_request_pr']) ? 'Request of PR Uploaded' : 'Request of PR Missing' ?>">
                                        PR
                                    </span>
                                </div>
                                <div class="small text-muted mt-1"><?= $opsDocsCount ?> / 3 uploaded</div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('/operations/projects/' . $item['id']) ?>" class="btn btn-outline-primary" title="View Project Details">
                                        View
                                    </a>
                                    <?php if (!$isSubmittedOpfin): ?>
                                        <a href="<?= url('/operations/projects/' . $item['id'] . '#ops-documents') ?>" class="btn btn-outline-secondary" title="Upload Documents">
                                            Upload
                                        </a>
                                    <?php endif; ?>
                                </div>
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
            <nav aria-label="Dashboard pagination">
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
