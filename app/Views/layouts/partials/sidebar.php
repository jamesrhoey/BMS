<?php
$userFullName = trim((string) ($currentUser['full_name'] ?? 'User'));
$userInitial = strtoupper((string) substr($userFullName !== '' ? $userFullName : 'U', 0, 1));
?>
<aside class="bms-sidebar" id="bmsSidebar" aria-label="Primary Navigation">
    <div class="bms-sidebar-header">
        <a href="<?= url('/dashboard') ?>" class="bms-sidebar-brand" title="PAGECOM INC. — Business Management System">
            <div class="bms-brand-logo-plate">
                <img src="<?= asset('images/pagecom-logo.png') ?>" alt="PAGECOM INC." class="bms-brand-logo">
            </div>
            
            <div class="bms-sidebar-brand-subtitle">Business Management System</div>
        </a>

        <button type="button"
                class="bms-sidebar-header-btn bms-collapse-only"
                data-sidebar-collapse
                title="Icon-only sidebar"
                aria-label="Collapse sidebar to icon-only view">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                <line x1="9" y1="3" x2="9" y2="21"></line>
                <polyline points="16 15 13 12 16 9"></polyline>
            </svg>
        </button>
        <button type="button"
                class="bms-sidebar-header-btn bms-expand-only"
                data-sidebar-expand
                title="Expand sidebar"
                aria-label="Expand sidebar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                <line x1="9" y1="3" x2="9" y2="21"></line>
                <polyline points="13 9 16 12 13 15"></polyline>
            </svg>
        </button>
    </div>
    <nav class="py-2 flex-grow-1">
        <div class="bms-sidebar-section"><span>Workspace</span></div>

        <?php if (!empty($isAdmin)): ?>
            <a href="<?= url('/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"
               title="Dashboard">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    </svg>
                </span>
                <span class="bms-nav-label">Dashboard</span>
            </a>

            <a href="<?= url('/projects') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'projects' ? 'active' : '' ?>"
               title="Projects">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Projects</span>
            </a>
        <?php elseif (!empty($isMarketingUser)): ?>
            <?php
            // Marketing Department Submenu & Dropdown Active State Resolution
            $mktQueryStr = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
            $mktQueryParsed = [];
            if ($mktQueryStr !== '') {
                parse_str($mktQueryStr, $mktQueryParsed);
            }
            $mktCurrentStatus = (string) ($mktQueryParsed['status'] ?? ($_GET['status'] ?? ''));
            $mktCurrentCompleteness = (string) ($mktQueryParsed['completeness'] ?? ($_GET['completeness'] ?? ''));
            $mktCurrentView = (string) ($mktQueryParsed['view'] ?? ($_GET['view'] ?? ''));
            $mktActiveNav = (string) ($activeNav ?? '');

            $isMktDashActive = ($mktActiveNav === 'marketing_dashboard');
            $isMktCreateActive = ($mktActiveNav === 'projects_create');
            $isMktDraftActive = ($mktActiveNav === 'marketing_projects' && $mktCurrentStatus === 'draft');
            $isMktReturnedActive = ($mktActiveNav === 'marketing_projects' && in_array($mktCurrentStatus, ['returned', 'Returned for Corrections', 'Returned to Marketing for Corrections', 'Returned to Marketing'], true));
            $isMktSubmittedActive = ($mktActiveNav === 'marketing_projects' && in_array($mktCurrentStatus, ['submitted', 'submitted_queue', 'pm_queue', 'Submitted to Project Management and Admin Finance', 'Resubmitted to Project Management and Admin Finance'], true));
            $isMktDocsActive = ($mktActiveNav === 'marketing_projects' && ($mktCurrentView === 'documents' || $mktCurrentCompleteness !== ''));
            $isMktAllActive = ($mktActiveNav === 'marketing_projects' && !$isMktDraftActive && !$isMktReturnedActive && !$isMktSubmittedActive && !$isMktDocsActive);

            $isMarketingGroupOpen = $isMktDashActive || $isMktCreateActive || $isMktDraftActive || $isMktReturnedActive || $isMktSubmittedActive || $isMktDocsActive || $isMktAllActive || str_starts_with($mktActiveNav, 'marketing_');
            ?>

            <!-- MARKETING DROPDOWN (Expandable/Collapsible) -->
            <div class="bms-nav-group <?= $isMarketingGroupOpen ? 'open' : '' ?>" data-nav-group="marketing-main">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isMarketingGroupOpen ? 'active-parent' : '' ?>"
                        data-nav-toggle="marketing-main"
                        aria-expanded="<?= $isMarketingGroupOpen ? 'true' : 'false' ?>"
                        aria-controls="nav-group-marketing-main"
                        title="Marketing">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('folder', 18) ?></span>
                    <span class="bms-nav-label">Marketing</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-marketing-main" role="region" aria-label="Marketing Submenu">
                    <!-- Dashboard -->
                    <a href="<?= url('/marketing/dashboard') ?>"
                       class="bms-subnav-link <?= $isMktDashActive ? 'active' : '' ?>"
                       title="Dashboard">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Dashboard</span>
                    </a>

                    <!-- Create Project -->
                    <?php if (!empty($canRegisterProject)): ?>
                        <a href="<?= url('/marketing/projects/create') ?>"
                           class="bms-subnav-link <?= $isMktCreateActive ? 'active' : '' ?>"
                           title="Create Project">
                            <span class="bms-subnav-bullet" aria-hidden="true"></span>
                            <span class="bms-nav-label">Create Project</span>
                        </a>
                    <?php endif; ?>

                    <!-- All Projects -->
                    <a href="<?= url('/marketing/projects') ?>"
                       class="bms-subnav-link <?= $isMktAllActive ? 'active' : '' ?>"
                       title="All Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">All Projects</span>
                    </a>

                    <!-- Draft Projects -->
                    <a href="<?= url('/marketing/projects?status=draft') ?>"
                       class="bms-subnav-link <?= $isMktDraftActive ? 'active' : '' ?>"
                       title="Draft Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Draft Projects</span>
                    </a>

                    <!-- Returned Projects -->
                    <a href="<?= url('/marketing/projects?status=returned') ?>"
                       class="bms-subnav-link <?= $isMktReturnedActive ? 'active' : '' ?>"
                       title="Returned Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned Projects</span>
                    </a>

                    <!-- Submitted Projects -->
                    <a href="<?= url('/marketing/projects?status=submitted_queue') ?>"
                       class="bms-subnav-link <?= $isMktSubmittedActive ? 'active' : '' ?>"
                       title="Submitted Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Submitted Projects</span>
                    </a>

                    <!-- Project Documents -->
                    <a href="<?= url('/marketing/projects?view=documents') ?>"
                       class="bms-subnav-link <?= $isMktDocsActive ? 'active' : '' ?>"
                       title="Project Documents">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Project Documents</span>
                    </a>
                </div>
            </div>
        <?php elseif (!empty($isProjectManagementUser)): ?>
            <?php
            // Project Management Department Submenu & Dropdown Active State Resolution
            $pmQueryStr = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
            $pmQueryParsed = [];
            if ($pmQueryStr !== '') {
                parse_str($pmQueryStr, $pmQueryParsed);
            }
            $pmCurrentStatus = (string) ($pmQueryParsed['status'] ?? ($_GET['status'] ?? ''));
            $pmActiveNav = (string) ($activeNav ?? '');

            // Active state resolution
            $isPmDashActive = in_array($pmActiveNav, ['pm_dashboard', 'dashboard', 'dashboard_dept'], true);
            $isPmIncomingActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['incoming', 'pending', 'awaiting'], true);
            $isPmDocActive = ($pmActiveNav === 'pm_projects' && in_array($pmCurrentStatus, ['verification', 'verification_queue', 'pm_queue', 'resubmitted'], true)) || $pmActiveNav === 'pm_verification';
            $isPmReturnedActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['returned', 'Returned for Corrections', 'Returned to Marketing for Corrections'], true);
            $isPmApprovedActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['approved', 'completed', 'verified', 'Approved by Project Management'], true);
            $isPmForwardedOpsActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['forwarded_to_ops', 'forwarded_ops', 'Forwarded to OPS', 'forwarded_to_operations'], true);
            $isPmHistoryActive = ($pmActiveNav === 'pm_verification_history') || ($pmActiveNav === 'pm_projects' && $pmCurrentStatus === 'history');

            $isPmGroupOpen = $isPmDashActive || $isPmIncomingActive || $isPmDocActive || $isPmReturnedActive || $isPmApprovedActive || $isPmForwardedOpsActive || $isPmHistoryActive || str_starts_with($pmActiveNav, 'pm_');
            ?>

            <!-- PROJECT MANAGEMENT DROPDOWN (Expandable/Collapsible) -->
            <div class="bms-nav-group <?= $isPmGroupOpen ? 'open' : '' ?>" data-nav-group="pm-main">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isPmGroupOpen ? 'active-parent' : '' ?>"
                        data-nav-toggle="pm-main"
                        aria-expanded="<?= $isPmGroupOpen ? 'true' : 'false' ?>"
                        aria-controls="nav-group-pm-main"
                        title="Project Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('folder', 18) ?></span>
                    <span class="bms-nav-label">Project Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-pm-main" role="region" aria-label="Project Management Submenu">
                    <!-- Dashboard -->
                    <a href="<?= url('/pm/dashboard') ?>"
                       class="bms-subnav-link <?= $isPmDashActive ? 'active' : '' ?>"
                       title="Dashboard">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Dashboard</span>
                    </a>

                    <!-- Incoming Projects -->
                    <a href="<?= url('/pm/projects?status=incoming') ?>"
                       class="bms-subnav-link <?= $isPmIncomingActive ? 'active' : '' ?>"
                       title="Incoming Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Incoming Projects</span>
                    </a>

                    <!-- Document Verification -->
                    <a href="<?= url('/pm/projects?status=verification') ?>"
                       class="bms-subnav-link <?= $isPmDocActive ? 'active' : '' ?>"
                       title="Document Verification">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Document Verification</span>
                    </a>

                    <!-- Returned Projects -->
                    <a href="<?= url('/pm/projects?status=returned') ?>"
                       class="bms-subnav-link <?= $isPmReturnedActive ? 'active' : '' ?>"
                       title="Returned Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned Projects</span>
                    </a>

                    <!-- Approved Projects -->
                    <a href="<?= url('/pm/projects?status=approved') ?>"
                       class="bms-subnav-link <?= $isPmApprovedActive ? 'active' : '' ?>"
                       title="Approved Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Approved Projects</span>
                    </a>

                    <!-- Forwarded to OPS -->
                    <a href="<?= url('/pm/projects?status=forwarded_to_ops') ?>"
                       class="bms-subnav-link <?= $isPmForwardedOpsActive ? 'active' : '' ?>"
                       title="Forwarded to OPS">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Forwarded to OPS</span>
                    </a>

                    <!-- Verification History -->
                    <a href="<?= url('/pm/verification-history') ?>"
                       class="bms-subnav-link <?= $isPmHistoryActive ? 'active' : '' ?>"
                       title="Verification History">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Verification History</span>
                    </a>
                </div>
            </div>
        <?php elseif (!empty($isAdminFinanceUser)): ?>
            <?php
            // Admin Finance Department Submenu & Dropdown Active State Resolution
            $afinQueryStr = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
            $afinQueryParsed = [];
            if ($afinQueryStr !== '') {
                parse_str($afinQueryStr, $afinQueryParsed);
            }
            $afinCurrentStatus = (string) ($afinQueryParsed['status'] ?? ($_GET['status'] ?? ''));
            $afinActiveNav = (string) ($activeNav ?? '');

            // 1. Dashboard Active State
            $isAfinDashActive = in_array($afinActiveNav, ['afin_dashboard', 'dashboard', 'dashboard_dept'], true);

            // 2. Project Inbox Submenu
            $isAfinIncomingActive = ($afinActiveNav === 'afin_projects') && ($afinCurrentStatus === 'incoming_approved');
            $isAfinAllReceivedActive = ($afinActiveNav === 'afin_projects') && $afinCurrentStatus === '';
            $isAfinInboxGroupActive = $isAfinIncomingActive || $isAfinAllReceivedActive;

            // 3. Job Order Management Submenu
            $isAfinJomQueueActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['awaiting_jom', 'awaiting'], true);
            $isAfinJomUnderReviewActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['under_review', 'under_afin_review', 'Under Admin Finance Review'], true);
            $isAfinJomIssuedActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['jom_issued', 'JOM Issued', 'JOM Number Issued'], true);
            $isAfinJomGroupActive = $isAfinJomQueueActive || $isAfinJomUnderReviewActive || $isAfinJomIssuedActive;

            ?>

            <!-- DASHBOARD -->
            <a href="<?= url('/admin-finance/dashboard') ?>"
               class="bms-nav-link <?= $isAfinDashActive ? 'active' : '' ?>"
               title="Admin Finance Dashboard">
                <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('dashboard', 18) ?></span>
                <span class="bms-nav-label">Admin Finance Dashboard</span>
            </a>

            <!-- PROJECT INBOX (Dropdown) -->
            <div class="bms-nav-group <?= $isAfinInboxGroupActive ? 'open' : '' ?>" data-nav-group="afin-inbox">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isAfinInboxGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="afin-inbox"
                        aria-expanded="<?= $isAfinInboxGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-afin-inbox"
                        title="Project Inbox">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('inbox', 18) ?></span>
                    <span class="bms-nav-label">Project Inbox</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-afin-inbox" role="region" aria-label="Project Inbox Submenu">
                    <a href="<?= url('/admin-finance/projects?status=incoming_approved') ?>"
                       class="bms-subnav-link <?= $isAfinIncomingActive ? 'active' : '' ?>"
                       title="Incoming Approved Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Incoming Approved Projects</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects') ?>"
                       class="bms-subnav-link <?= $isAfinAllReceivedActive ? 'active' : '' ?>"
                       title="All Received Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">All Received Projects</span>
                    </a>
                </div>
            </div>

            <!-- JOB ORDER MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isAfinJomGroupActive ? 'open' : '' ?>" data-nav-group="afin-jom">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isAfinJomGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="afin-jom"
                        aria-expanded="<?= $isAfinJomGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-afin-jom"
                        title="Job Order Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('file-text', 18) ?></span>
                    <span class="bms-nav-label">Job Order Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-afin-jom" role="region" aria-label="Job Order Management Submenu">
                    <a href="<?= url('/admin-finance/projects?status=awaiting_jom') ?>"
                       class="bms-subnav-link <?= $isAfinJomQueueActive ? 'active' : '' ?>"
                       title="JOM Issuance Queue">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">JOM Issuance Queue</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=under_review') ?>"
                       class="bms-subnav-link <?= $isAfinJomUnderReviewActive ? 'active' : '' ?>"
                       title="Projects Under JOM Review">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Projects Under JOM Review</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=jom_issued') ?>"
                       class="bms-subnav-link <?= $isAfinJomIssuedActive ? 'active' : '' ?>"
                       title="Issued JOM Records">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Issued JOM Records</span>
                    </a>
                </div>
            </div>

        <?php elseif (!empty($isOperationsUser)): ?>
            <?php
            // Operations Department Submenu & Dropdown Active State Resolution
            $opsQueryStr = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
            $opsQueryParsed = [];
            if ($opsQueryStr !== '') {
                parse_str($opsQueryStr, $opsQueryParsed);
            }
            $opsCurrentStatus = (string) ($opsQueryParsed['status'] ?? ($_GET['status'] ?? ''));
            $opsCurrentCategory = (string) ($opsQueryParsed['category'] ?? ($_GET['category'] ?? ''));
            $opsActiveNav = (string) ($activeNav ?? '');

            // Active state resolution
            $isOpsDashActive = in_array($opsActiveNav, ['ops_dashboard', 'dashboard', 'dashboard_dept'], true);
            $isOpsIncomingActive = ($opsActiveNav === 'ops_projects') && in_array($opsCurrentStatus, ['incoming', 'pending', 'received'], true);
            $isOpsMrcActive = ($opsActiveNav === 'ops_projects') && strtolower($opsCurrentCategory) === 'mrc';
            $isOpsSpecialActive = ($opsActiveNav === 'ops_projects') && in_array(strtolower($opsCurrentCategory), ['special_project', 'special project'], true);
            $isOpsDocsActive = ($opsActiveNav === 'ops_projects') && in_array($opsCurrentStatus, ['documents_complete', 'complete', 'docs'], true);
            $isOpsSubmittedActive = ($opsActiveNav === 'ops_projects') && in_array($opsCurrentStatus, ['submitted_opfin', 'submitted_to_ops_finance', 'submitted'], true);
            $isOpsHistoryActive = ($opsActiveNav === 'ops_history') || ($opsActiveNav === 'ops_projects' && $opsCurrentStatus === 'history');

            $isOpsGroupOpen = $isOpsDashActive || $isOpsIncomingActive || $isOpsMrcActive || $isOpsSpecialActive || $isOpsDocsActive || $isOpsSubmittedActive || $isOpsHistoryActive || str_starts_with($opsActiveNav, 'ops_');
            ?>

            <!-- OPS (OPERATIONS) DROPDOWN (Expandable/Collapsible) -->
            <div class="bms-nav-group <?= $isOpsGroupOpen ? 'open' : '' ?>" data-nav-group="ops-main">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpsGroupOpen ? 'active-parent' : '' ?>"
                        data-nav-toggle="ops-main"
                        aria-expanded="<?= $isOpsGroupOpen ? 'true' : 'false' ?>"
                        aria-controls="nav-group-ops-main"
                        title="OPS (Operations)">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('briefcase', 18) ?></span>
                    <span class="bms-nav-label">OPS (Operations)</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-ops-main" role="region" aria-label="Operations Submenu">
                    <!-- Dashboard -->
                    <a href="<?= url('/operations/dashboard') ?>"
                       class="bms-subnav-link <?= $isOpsDashActive ? 'active' : '' ?>"
                       title="Dashboard">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Dashboard</span>
                    </a>

                    <!-- Incoming Projects -->
                    <a href="<?= url('/operations/projects?status=incoming') ?>"
                       class="bms-subnav-link <?= $isOpsIncomingActive ? 'active' : '' ?>"
                       title="Incoming Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Incoming Projects</span>
                    </a>

                    <!-- MRC Projects -->
                    <a href="<?= url('/operations/projects?category=mrc') ?>"
                       class="bms-subnav-link <?= $isOpsMrcActive ? 'active' : '' ?>"
                       title="MRC Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">MRC Projects</span>
                    </a>

                    <!-- Special Projects -->
                    <a href="<?= url('/operations/projects?category=special_project') ?>"
                       class="bms-subnav-link <?= $isOpsSpecialActive ? 'active' : '' ?>"
                       title="Special Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Special Projects</span>
                    </a>

                    <!-- Project Documents -->
                    <a href="<?= url('/operations/projects?status=documents_complete') ?>"
                       class="bms-subnav-link <?= $isOpsDocsActive ? 'active' : '' ?>"
                       title="Project Documents">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Project Documents</span>
                    </a>

                    <!-- Submitted to OPS Finance -->
                    <a href="<?= url('/operations/projects?status=submitted_opfin') ?>"
                       class="bms-subnav-link <?= $isOpsSubmittedActive ? 'active' : '' ?>"
                       title="Submitted to OPS Finance">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Submitted to OPS Finance</span>
                    </a>

                    <!-- Project History -->
                    <a href="<?= url('/operations/history') ?>"
                       class="bms-subnav-link <?= $isOpsHistoryActive ? 'active' : '' ?>"
                       title="Project History">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Project History</span>
                    </a>
                </div>
            </div>
        <?php elseif (!empty($isOpsFinanceUser)): ?>
            <?php $opfinNav = (string) ($activeNav ?? ''); $opfinOpen = str_starts_with($opfinNav, 'opfin_'); ?>
            <div class="bms-nav-group <?= $opfinOpen ? 'open' : '' ?>" data-nav-group="ops-finance">
                <button type="button" class="bms-nav-dropdown-toggle <?= $opfinOpen ? 'active-parent' : '' ?>" data-nav-toggle="ops-finance" aria-expanded="<?= $opfinOpen ? 'true' : 'false' ?>" aria-controls="nav-group-ops-finance" title="OPS Finance">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('briefcase', 18) ?></span><span class="bms-nav-label">OPS Finance</span><span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-ops-finance" role="region" aria-label="OPS Finance Submenu">
                    <a href="<?= url('/ops-finance/dashboard') ?>" class="bms-subnav-link <?= $opfinNav === 'opfin_dashboard' ? 'active' : '' ?>"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Dashboard</span></a>
                    <a href="<?= url('/ops-finance/projects') ?>" class="bms-subnav-link <?= $opfinNav === 'opfin_projects' ? 'active' : '' ?>"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Incoming Projects</span></a>
                    <a href="<?= url('/ops-finance/projects?category=mrc') ?>" class="bms-subnav-link"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">MRC Projects</span></a>
                    <a href="<?= url('/ops-finance/projects?category=special_project') ?>" class="bms-subnav-link"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Special Projects</span></a>
                    <a href="<?= url('/ops-finance/projects') ?>" class="bms-subnav-link"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Project Documents</span></a>
                    <a href="<?= url('/ops-finance/projects?status=Awaiting PR') ?>" class="bms-subnav-link"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Pending PR Upload</span></a>
                    <a href="<?= url('/ops-finance/projects?status=Awaiting Canvassing Sheets') ?>" class="bms-subnav-link"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Pending Canvassing Sheets</span></a>
                    <a href="<?= url('/ops-finance/projects?status=Submitted to Admin Finance') ?>" class="bms-subnav-link"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Submitted to Admin Finance</span></a>
                    <a href="<?= url('/ops-finance/history') ?>" class="bms-subnav-link <?= $opfinNav === 'opfin_history' ? 'active' : '' ?>"><span class="bms-subnav-bullet"></span><span class="bms-nav-label">Project History</span></a>
                </div>
            </div>
        <?php elseif (!empty($isOpsFinanceUser) && false): ?>
            <?php
            // Resolve current OPS Finance filter states for deep-linking & auto-expansion
            $opfinQueryStr = (string) ($_SERVER['QUERY_STRING'] ?? '');
            $opfinQueryParsed = [];
            parse_str($opfinQueryStr, $opfinQueryParsed);
            $opfinCurrentStatus = (string) ($opfinQueryParsed['status'] ?? ($_GET['status'] ?? ''));
            $opfinActiveNav = (string) ($activeNav ?? '');

            // 1. Dashboard Active State
            $isOpfinDashActive = in_array($opfinActiveNav, ['opfin_dashboard', 'dashboard', 'dashboard_dept'], true);

            // 2. Purchase Request Inbox Submenu
            $isOpfinIncomingPrActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['awaiting_review', 'incoming'], true);
            $isOpfinAllPrsActive = ($opfinActiveNav === 'opfin_projects') && ($opfinCurrentStatus === '');
            $isOpfinPendingPrActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['under_review', 'Under OPS Finance Review'], true);
            $isOpfinReturnedPrActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['returned_prs', 'returned', 'Returned for Corrections'], true);
            $isOpfinProcessedPrActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['processed_prs', 'processed'], true);
            $isOpfinPrInboxGroupActive = $isOpfinIncomingPrActive || $isOpfinAllPrsActive || $isOpfinPendingPrActive || $isOpfinReturnedPrActive || $isOpfinProcessedPrActive;

            // 3. Actual BOM Management Submenu
            $isOpfinBomPrepActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['pending_actual_bom', 'preparation'], true);
            $isOpfinDraftBomActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['draft_bom', 'draft', 'Draft'], true);
            $isOpfinAllBomsActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['all_boms', 'records'], true);
            $isOpfinActualBomGroupActive = $isOpfinBomPrepActive || $isOpfinDraftBomActive || $isOpfinAllBomsActive;

            // 4. Actual BOM Submission Submenu
            $isOpfinPendingSubActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['pending_submission'], true);
            $isOpfinSubmittedAfinActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['submitted_to_afin', 'submitted'], true);
            $isOpfinReturnedBomActive = ($opfinActiveNav === 'opfin_projects') && in_array($opfinCurrentStatus, ['returned_bom', 'returned_actual_bom'], true);
            $isOpfinBomSubGroupActive = $isOpfinPendingSubActive || $isOpfinSubmittedAfinActive || $isOpfinReturnedBomActive;
            ?>

            <!-- DASHBOARD -->
            <a href="<?= url('/ops-finance/dashboard') ?>"
               class="bms-nav-link <?= $isOpfinDashActive ? 'active' : '' ?>"
               title="OPS Finance Dashboard">
                <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('dashboard', 18) ?></span>
                <span class="bms-nav-label">OPS Finance Dashboard</span>
            </a>

            <!-- PURCHASE REQUEST INBOX (Dropdown) -->
            <div class="bms-nav-group <?= $isOpfinPrInboxGroupActive ? 'open' : '' ?>" data-nav-group="opfin-pr-inbox">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpfinPrInboxGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="opfin-pr-inbox"
                        aria-expanded="<?= $isOpfinPrInboxGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-opfin-pr-inbox"
                        title="Purchase Request Inbox">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('inbox', 18) ?></span>
                    <span class="bms-nav-label">Purchase Request Inbox</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-opfin-pr-inbox" role="region" aria-label="Purchase Request Inbox Submenu">
                    <a href="<?= url('/ops-finance/projects?status=awaiting_review') ?>"
                       class="bms-subnav-link <?= $isOpfinIncomingPrActive ? 'active' : '' ?>"
                       title="Incoming Purchase Requests">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Incoming Purchase Requests</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects') ?>"
                       class="bms-subnav-link <?= $isOpfinAllPrsActive ? 'active' : '' ?>"
                       title="All Received PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">All Received PRs</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=under_review') ?>"
                       class="bms-subnav-link <?= $isOpfinPendingPrActive ? 'active' : '' ?>"
                       title="Pending PR Review">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Pending PR Review</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=returned_prs') ?>"
                       class="bms-subnav-link <?= $isOpfinReturnedPrActive ? 'active' : '' ?>"
                       title="Returned PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned PRs</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=processed_prs') ?>"
                       class="bms-subnav-link <?= $isOpfinProcessedPrActive ? 'active' : '' ?>"
                       title="Processed PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Processed PRs</span>
                    </a>
                </div>
            </div>

            <!-- ACTUAL BOM MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isOpfinActualBomGroupActive ? 'open' : '' ?>" data-nav-group="opfin-actual-bom">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpfinActualBomGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="opfin-actual-bom"
                        aria-expanded="<?= $isOpfinActualBomGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-opfin-actual-bom"
                        title="Actual BOM Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('layers', 18) ?></span>
                    <span class="bms-nav-label">Actual BOM Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-opfin-actual-bom" role="region" aria-label="Actual BOM Management Submenu">
                    <a href="<?= url('/ops-finance/projects?status=pending_actual_bom') ?>"
                       class="bms-subnav-link <?= $isOpfinBomPrepActive ? 'active' : '' ?>"
                       title="Actual BOM Preparation">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Actual BOM Preparation</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=draft_bom') ?>"
                       class="bms-subnav-link <?= $isOpfinDraftBomActive ? 'active' : '' ?>"
                       title="Draft Actual BOMs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Draft Actual BOMs</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=all_boms') ?>"
                       class="bms-subnav-link <?= $isOpfinAllBomsActive ? 'active' : '' ?>"
                       title="Actual BOM Records">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Actual BOM Records</span>
                    </a>
                </div>
            </div>

            <!-- ACTUAL BOM SUBMISSION (Dropdown) -->
            <div class="bms-nav-group <?= $isOpfinBomSubGroupActive ? 'open' : '' ?>" data-nav-group="opfin-bom-submission">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpfinBomSubGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="opfin-bom-submission"
                        aria-expanded="<?= $isOpfinBomSubGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-opfin-bom-submission"
                        title="Actual BOM Submission">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('send', 18) ?></span>
                    <span class="bms-nav-label">Actual BOM Submission</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-opfin-bom-submission" role="region" aria-label="Actual BOM Submission Submenu">
                    <a href="<?= url('/ops-finance/projects?status=pending_submission') ?>"
                       class="bms-subnav-link <?= $isOpfinPendingSubActive ? 'active' : '' ?>"
                       title="Pending Submission">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Pending Submission</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=submitted_to_afin') ?>"
                       class="bms-subnav-link <?= $isOpfinSubmittedAfinActive ? 'active' : '' ?>"
                       title="Submitted Actual BOMs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Submitted Actual BOMs</span>
                    </a>
                    <a href="<?= url('/ops-finance/projects?status=returned_bom') ?>"
                       class="bms-subnav-link <?= $isOpfinReturnedBomActive ? 'active' : '' ?>"
                       title="Returned Actual BOMs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned Actual BOMs</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= url('/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'dashboard_dept' ? 'active' : '' ?>"
               title="Dashboard">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    </svg>
                </span>
                <span class="bms-nav-label">Dashboard</span>
            </a>

            <a href="<?= url('/projects') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'projects' ? 'active' : '' ?>"
               title="Projects">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Projects</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($canRegisterProject) && empty($isMarketingUser)): ?>
            <a href="<?= url('/projects/create') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'projects_create' ? 'active' : '' ?>"
               title="Register Project">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                </span>
                <span class="bms-nav-label">Register Project</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($isAdmin)): ?>
            <div class="bms-sidebar-section"><span>Marketing</span></div>

            <a href="<?= url('/marketing/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'marketing_dashboard' ? 'active' : '' ?>"
               title="Marketing — Dashboard">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </span>
                <span class="bms-nav-label">Dashboard</span>
            </a>

            <a href="<?= url('/marketing/projects') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'marketing_projects' ? 'active' : '' ?>"
               title="Marketing — Projects">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Projects</span>
            </a>

            <div class="bms-sidebar-section"><span>Project Management</span></div>

            <a href="<?= url('/pm/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'pm_dashboard' ? 'active' : '' ?>"
               title="Project Management — Dashboard">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </span>
                <span class="bms-nav-label">Dashboard</span>
            </a>

            <a href="<?= url('/pm/projects') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'pm_projects' ? 'active' : '' ?>"
               title="Project Management — Projects">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Projects</span>
            </a>

            <div class="bms-sidebar-section"><span>Admin Finance / Ops / OPS Fin</span></div>

            <a href="<?= url('/admin-finance/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'afin_dashboard' ? 'active' : '' ?>"
               title="Admin Finance — JOM & Actual BOM">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </span>
                <span class="bms-nav-label">Admin Finance</span>
            </a>

            <a href="<?= url('/operations/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'ops_dashboard' ? 'active' : '' ?>"
               title="Operations — Purchase Requests">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Operations PRs</span>
            </a>

            <a href="<?= url('/ops-finance/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'opfin_dashboard' ? 'active' : '' ?>"
               title="OPS Finance — Actual BOM">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">OPS Finance BOM</span>
            </a>

            <div class="bms-sidebar-section"><span>Administration</span></div>

            <a href="<?= url('/users') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"
               title="User Management">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">User Management</span>
            </a>

            <a href="<?= url('/departments') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'departments' ? 'active' : '' ?>"
               title="Departments">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                        <line x1="9" y1="6" x2="9.01" y2="6"></line>
                        <line x1="15" y1="6" x2="15.01" y2="6"></line>
                        <line x1="9" y1="10" x2="9.01" y2="10"></line>
                        <line x1="15" y1="10" x2="15.01" y2="10"></line>
                        <line x1="9" y1="14" x2="9.01" y2="14"></line>
                        <line x1="15" y1="14" x2="15.01" y2="14"></line>
                        <path d="M9 22v-4h6v4"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Departments</span>
            </a>

            <a href="<?= url('/activity-logs') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'activity_logs' ? 'active' : '' ?>"
               title="Activity Logs">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"></circle>
                        <polyline points="12 7 12 12 15 15"></polyline>
                    </svg>
                </span>
                <span class="bms-nav-label">Activity Logs</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($canAccessIatms) && empty($isOpsFinanceUser) && empty($isAdminFinanceUser)): ?>
            <?php
            $iatmsNav = (string) ($activeNav ?? '');
            $isIatmsFormsOpen = in_array($iatmsNav, ['iatms_stock_in', 'iatms_stock_in_create', 'iatms_stock_out', 'iatms_stock_out_create', 'iatms_ending_balance'], true);
            $isIatmsWhOpen = in_array($iatmsNav, ['iatms_products', 'iatms_units', 'iatms_warranties'], true);
            $isIatmsFinOpen = in_array($iatmsNav, ['iatms_invoices', 'iatms_collections'], true);
            ?>
            <div class="bms-sidebar-section"><span>Inventory &amp; Assets</span></div>

            <!-- DIGITIZED PAPER FORMS (Dropdown) -->
            <div class="bms-nav-group <?= $isIatmsFormsOpen ? 'open' : '' ?>" data-nav-group="iatms-forms">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isIatmsFormsOpen ? 'active-parent' : '' ?>"
                        data-nav-toggle="iatms-forms"
                        aria-expanded="<?= $isIatmsFormsOpen ? 'true' : 'false' ?>"
                        aria-controls="nav-group-iatms-forms"
                        title="Digitized Paper Forms">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('file-text', 18) ?></span>
                    <span class="bms-nav-label">Digitized Forms</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-iatms-forms" role="region" aria-label="Digitized Forms Submenu">
                    <a href="<?= url('/inventory/stock-in') ?>" class="bms-subnav-link <?= in_array($iatmsNav, ['iatms_stock_in', 'iatms_stock_in_create'], true) ? 'active' : '' ?>" title="Stock In (Form 1)">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Stock In (Form 1)</span>
                    </a>
                    <a href="<?= url('/inventory/stock-out') ?>" class="bms-subnav-link <?= in_array($iatmsNav, ['iatms_stock_out', 'iatms_stock_out_create'], true) ? 'active' : '' ?>" title="Stock Out (Form 2)">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Stock Out (Form 2)</span>
                    </a>
                    <a href="<?= url('/inventory/ending-balance') ?>" class="bms-subnav-link <?= $iatmsNav === 'iatms_ending_balance' ? 'active' : '' ?>" title="Ending Balance (Form 3)">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Ending Balance (Form 3)</span>
                    </a>
                </div>
            </div>

            <!-- WAREHOUSE & SERIALS (Dropdown) -->
            <div class="bms-nav-group <?= $isIatmsWhOpen ? 'open' : '' ?>" data-nav-group="iatms-warehouse">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isIatmsWhOpen ? 'active-parent' : '' ?>"
                        data-nav-toggle="iatms-warehouse"
                        aria-expanded="<?= $isIatmsWhOpen ? 'true' : 'false' ?>"
                        aria-controls="nav-group-iatms-warehouse"
                        title="Warehouse & Serials">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('layers', 18) ?></span>
                    <span class="bms-nav-label">Warehouse &amp; Serials</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-iatms-warehouse" role="region" aria-label="Warehouse Submenu">
                    <a href="<?= url('/inventory/products') ?>" class="bms-subnav-link <?= $iatmsNav === 'iatms_products' ? 'active' : '' ?>" title="Product Catalog">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Product Catalog</span>
                    </a>
                    <a href="<?= url('/inventory/units') ?>" class="bms-subnav-link <?= $iatmsNav === 'iatms_units' ? 'active' : '' ?>" title="Serialized Units">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Serialized Units</span>
                    </a>
                    <a href="<?= url('/warranties') ?>" class="bms-subnav-link <?= $iatmsNav === 'iatms_warranties' ? 'active' : '' ?>" title="Warranties">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Warranty Tracking</span>
                    </a>
                </div>
            </div>

            <?php if (!empty($canViewIatmsFinance)): ?>
                <!-- BILLING & COLLECTIONS (Dropdown) -->
                <div class="bms-nav-group <?= $isIatmsFinOpen ? 'open' : '' ?>" data-nav-group="iatms-billing">
                    <button type="button"
                            class="bms-nav-dropdown-toggle <?= $isIatmsFinOpen ? 'active-parent' : '' ?>"
                            data-nav-toggle="iatms-billing"
                            aria-expanded="<?= $isIatmsFinOpen ? 'true' : 'false' ?>"
                            aria-controls="nav-group-iatms-billing"
                            title="Billing & Collections">
                        <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('briefcase', 18) ?></span>
                        <span class="bms-nav-label">Billing &amp; Collections</span>
                        <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                    </button>
                    <div class="bms-subnav-menu" id="nav-group-iatms-billing" role="region" aria-label="Billing Submenu">
                        <a href="<?= url('/invoices') ?>" class="bms-subnav-link <?= $iatmsNav === 'iatms_invoices' ? 'active' : '' ?>" title="Invoices">
                            <span class="bms-subnav-bullet" aria-hidden="true"></span>
                            <span class="bms-nav-label">Invoices</span>
                        </a>
                        <a href="<?= url('/collections') ?>" class="bms-subnav-link <?= $iatmsNav === 'iatms_collections' ? 'active' : '' ?>" title="Collections">
                            <span class="bms-subnav-bullet" aria-hidden="true"></span>
                            <span class="bms-nav-label">Collections</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <a href="<?= url('/reports') ?>"
               class="bms-nav-link <?= $iatmsNav === 'iatms_reports' ? 'active' : '' ?>"
               title="Reports & Digitized Form Exports">
                <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('activity', 18) ?></span>
                <span class="bms-nav-label">Reports &amp; Forms</span>
            </a>

            <?php if (!empty($isAdmin)): ?>
                <a href="<?= url('/admin/data-integrity') ?>"
                   class="bms-nav-link <?= $iatmsNav === 'iatms_integrity' ? 'active' : '' ?>"
                   title="11-Rule Data Integrity Diagnostics">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('file-check', 18) ?></span>
                    <span class="bms-nav-label">Data Integrity</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="bms-sidebar-section"><span>Account</span></div>
        <a href="<?= url('/notifications') ?>"
           class="bms-nav-link justify-content-between position-relative <?= ($activeNav ?? '') === 'notifications' ? 'active' : '' ?>"
           title="Notifications">
            <span class="bms-nav-item-main d-flex align-items-center gap-2">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Notifications</span>
            </span>
            <?php if (!empty($unreadNotificationCount) && (int) $unreadNotificationCount > 0): ?>
                <span class="badge rounded-pill bg-danger bms-nav-badge"><?= (int) $unreadNotificationCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= url('/profile') ?>"
           class="bms-nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>"
           title="My Profile">
            <span class="bms-nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </span>
            <span class="bms-nav-label">My Profile</span>
        </a>
    </nav>

    <?php if (!empty($currentUser)): ?>
        <div class="bms-sidebar-user small"
             title="<?= e($userFullName) ?> (<?= e($currentUser['role_name'] ?? '') ?><?= !empty($currentUser['department_code']) ? ' • ' . e($currentUser['department_code']) : '' ?>)">
            <div class="bms-sidebar-user-header">
                <div class="bms-user-initials" aria-hidden="true"><?= e($userInitial) ?></div>
                <div class="bms-user-meta min-w-0">
                    <div class="bms-user-meta-name text-truncate"><?= e($userFullName) ?></div>
                    <div class="bms-user-meta-role text-truncate">
                        <?= e($currentUser['role_name']) ?>
                        <?php if (!empty($currentUser['department_code'])): ?>
                            &bull; <?= e($currentUser['department_code']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-column gap-1 mt-1">
                <a href="<?= url('/profile') ?>" class="bms-sidebar-quick-btn" title="View Profile">
                    <?= bms_icon('user', 14) ?>
                    <span>My Profile</span>
                </a>
                <form method="POST" action="<?= url('/logout') ?>" class="m-0 p-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="bms-sidebar-quick-btn border-0 text-start w-100" title="Sign Out">
                        <?= bms_icon('logout', 14) ?>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</aside>
