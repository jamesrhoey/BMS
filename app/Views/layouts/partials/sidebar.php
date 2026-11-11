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
            <div class="bms-sidebar-brand-name">PAGECOM INC.</div>
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
            $mktActiveNav = (string) ($activeNav ?? '');

            // 1. Project Management Submenu
            $isMktRegActive = ($mktActiveNav === 'projects_create');
            $isMktAllActive = ($mktActiveNav === 'projects');
            $isMktMyActive = ($mktActiveNav === 'marketing_projects') && $mktCurrentStatus === '' && $mktCurrentCompleteness === '';
            $isMktPmGroupActive = $isMktRegActive || $isMktMyActive || $isMktAllActive;

            // 2. Document Management Submenu
            $isMktReqDocsActive = ($mktActiveNav === 'marketing_projects') && ($mktCurrentCompleteness === 'incomplete');
            $isMktSubDocsActive = ($mktActiveNav === 'marketing_projects') && ($mktCurrentCompleteness === 'complete');
            $isMktDocsGroupActive = $isMktReqDocsActive || $isMktSubDocsActive;

            // 3. Project Tracking Submenu
            $isMktPendingActive = ($mktActiveNav === 'marketing_projects') && ($mktCurrentStatus === 'pm_queue');
            $isMktReturnedActive = ($mktActiveNav === 'marketing_projects') && ($mktCurrentStatus === 'Returned for Corrections');
            $isMktApprovedActive = ($mktActiveNav === 'marketing_projects') && ($mktCurrentStatus === 'Approved by Project Management');
            $isMktForwardedActive = ($mktActiveNav === 'marketing_projects') && in_array($mktCurrentStatus, ['Forwarded to Admin Finance', 'Forwarded to Operations'], true);
            $isMktTrackingGroupActive = $isMktPendingActive || $isMktReturnedActive || $isMktApprovedActive || $isMktForwardedActive;

            $canViewAllProjects = !empty($isAdmin) || \App\Services\AuthorizationService::hasPermission($currentUser ?? null, 'projects.view_all');
            ?>

            <!-- DASHBOARD -->
            <a href="<?= url('/marketing/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'marketing_dashboard' ? 'active' : '' ?>"
               title="Dashboard">
                <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('dashboard', 18) ?></span>
                <span class="bms-nav-label">Dashboard</span>
            </a>

            <!-- PROJECT MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isMktPmGroupActive ? 'open' : '' ?>" data-nav-group="marketing-pm">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isMktPmGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="marketing-pm"
                        aria-expanded="<?= $isMktPmGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-marketing-pm"
                        title="Project Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('folder', 18) ?></span>
                    <span class="bms-nav-label">Project Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-marketing-pm" role="region" aria-label="Project Management Submenu">
                    <?php if (!empty($canRegisterProject)): ?>
                        <a href="<?= url('/marketing/projects/create') ?>"
                           class="bms-subnav-link <?= $isMktRegActive ? 'active' : '' ?>"
                           title="Project Registration">
                            <span class="bms-subnav-bullet" aria-hidden="true"></span>
                            <span class="bms-nav-label">Project Registration</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('/marketing/projects') ?>"
                       class="bms-subnav-link <?= $isMktMyActive ? 'active' : '' ?>"
                       title="My Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">My Projects</span>
                    </a>
                    <?php if ($canViewAllProjects): ?>
                        <a href="<?= url('/projects') ?>"
                           class="bms-subnav-link <?= $isMktAllActive ? 'active' : '' ?>"
                           title="All Projects">
                            <span class="bms-subnav-bullet" aria-hidden="true"></span>
                            <span class="bms-nav-label">All Projects</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DOCUMENT MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isMktDocsGroupActive ? 'open' : '' ?>" data-nav-group="marketing-docs">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isMktDocsGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="marketing-docs"
                        aria-expanded="<?= $isMktDocsGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-marketing-docs"
                        title="Document Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('file-text', 18) ?></span>
                    <span class="bms-nav-label">Document Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-marketing-docs" role="region" aria-label="Document Management Submenu">
                    <a href="<?= url('/marketing/projects?completeness=incomplete') ?>"
                       class="bms-subnav-link <?= $isMktReqDocsActive ? 'active' : '' ?>"
                       title="Required Project Documents">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Required Project Documents</span>
                    </a>
                    <a href="<?= url('/marketing/projects?completeness=complete') ?>"
                       class="bms-subnav-link <?= $isMktSubDocsActive ? 'active' : '' ?>"
                       title="Submitted Documents">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Submitted Documents</span>
                    </a>
                </div>
            </div>

            <!-- PROJECT TRACKING (Dropdown) -->
            <div class="bms-nav-group <?= $isMktTrackingGroupActive ? 'open' : '' ?>" data-nav-group="marketing-tracking">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isMktTrackingGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="marketing-tracking"
                        aria-expanded="<?= $isMktTrackingGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-marketing-tracking"
                        title="Project Tracking">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('activity', 18) ?></span>
                    <span class="bms-nav-label">Project Tracking</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-marketing-tracking" role="region" aria-label="Project Tracking Submenu">
                    <a href="<?= url('/marketing/projects?status=pm_queue') ?>"
                       class="bms-subnav-link <?= $isMktPendingActive ? 'active' : '' ?>"
                       title="Pending Reviews">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Pending Reviews</span>
                    </a>
                    <a href="<?= url('/marketing/projects?status=' . urlencode('Returned for Corrections')) ?>"
                       class="bms-subnav-link <?= $isMktReturnedActive ? 'active' : '' ?>"
                       title="Returned Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned Projects</span>
                    </a>
                    <a href="<?= url('/marketing/projects?status=' . urlencode('Approved by Project Management')) ?>"
                       class="bms-subnav-link <?= $isMktApprovedActive ? 'active' : '' ?>"
                       title="Approved Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Approved Projects</span>
                    </a>
                    <a href="<?= url('/marketing/projects?status=' . urlencode('Forwarded to Admin Finance')) ?>"
                       class="bms-subnav-link <?= $isMktForwardedActive ? 'active' : '' ?>"
                       title="Forwarded Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Forwarded Projects</span>
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

            // 1. Dashboard Active State
            $isPmDashActive = in_array($pmActiveNav, ['pm_dashboard', 'dashboard', 'dashboard_dept'], true);

            // 2. Project Intake Submenu
            $isPmIncomingActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['pending', 'awaiting'], true);
            $isPmAllReceivedActive = ($pmActiveNav === 'pm_projects') && $pmCurrentStatus === '';
            $isPmIntakeGroupActive = $isPmIncomingActive || $isPmAllReceivedActive;

            // 3. Document Verification Submenu
            $isPmDocQueueActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['verification_queue', 'pm_queue'], true);
            $isPmDocResubActive = ($pmActiveNav === 'pm_projects') && ($pmCurrentStatus === 'resubmitted');
            $isPmVerificationGroupActive = $isPmDocQueueActive || $isPmDocResubActive;

            // 4. Project Review Submenu
            $isPmUnderReviewActive = ($pmActiveNav === 'pm_projects') && ($pmCurrentStatus === 'under_review' || $pmCurrentStatus === 'Under Review');
            $isPmReturnedActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['returned', 'Returned for Corrections', 'Returned to Marketing for Corrections'], true);
            $isPmReviewGroupActive = $isPmUnderReviewActive || $isPmReturnedActive;

            // 5. Project Approval Submenu
            $isPmApprovedActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['approved', 'completed', 'verified', 'Approved by Project Management'], true);
            $isPmApprovalGroupActive = $isPmApprovedActive;

            // 6. Project Forwarding Submenu
            $isPmReadyForwardActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['ready_for_admin_finance', 'ready_forward'], true);
            $isPmForwardedActive = ($pmActiveNav === 'pm_projects') && in_array($pmCurrentStatus, ['forwarded_to_admin_finance', 'forwarded', 'Forwarded to Admin Finance'], true);
            $isPmForwardingGroupActive = $isPmReadyForwardActive || $isPmForwardedActive;
            ?>

            <!-- DASHBOARD -->
            <a href="<?= url('/pm/dashboard') ?>"
               class="bms-nav-link <?= $isPmDashActive ? 'active' : '' ?>"
               title="Project Management Dashboard">
                <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('dashboard', 18) ?></span>
                <span class="bms-nav-label">Project Management Dashboard</span>
            </a>

            <!-- PROJECT INTAKE (Dropdown) -->
            <div class="bms-nav-group <?= $isPmIntakeGroupActive ? 'open' : '' ?>" data-nav-group="pm-intake">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isPmIntakeGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="pm-intake"
                        aria-expanded="<?= $isPmIntakeGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-pm-intake"
                        title="Project Intake">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('inbox', 18) ?></span>
                    <span class="bms-nav-label">Project Intake</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-pm-intake" role="region" aria-label="Project Intake Submenu">
                    <a href="<?= url('/pm/projects?status=pending') ?>"
                       class="bms-subnav-link <?= $isPmIncomingActive ? 'active' : '' ?>"
                       title="Incoming Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Incoming Projects</span>
                    </a>
                    <a href="<?= url('/pm/projects') ?>"
                       class="bms-subnav-link <?= $isPmAllReceivedActive ? 'active' : '' ?>"
                       title="All Received Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">All Received Projects</span>
                    </a>
                </div>
            </div>

            <!-- DOCUMENT VERIFICATION (Dropdown) -->
            <div class="bms-nav-group <?= $isPmVerificationGroupActive ? 'open' : '' ?>" data-nav-group="pm-verification">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isPmVerificationGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="pm-verification"
                        aria-expanded="<?= $isPmVerificationGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-pm-verification"
                        title="Document Verification">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('file-check', 18) ?></span>
                    <span class="bms-nav-label">Document Verification</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-pm-verification" role="region" aria-label="Document Verification Submenu">
                    <a href="<?= url('/pm/projects?status=verification_queue') ?>"
                       class="bms-subnav-link <?= $isPmDocQueueActive ? 'active' : '' ?>"
                       title="Document Verification Queue">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Document Verification Queue</span>
                    </a>
                    <a href="<?= url('/pm/projects?status=resubmitted') ?>"
                       class="bms-subnav-link <?= $isPmDocResubActive ? 'active' : '' ?>"
                       title="Resubmitted Documents">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Resubmitted Documents</span>
                    </a>
                </div>
            </div>

            <!-- PROJECT REVIEW (Dropdown) -->
            <div class="bms-nav-group <?= $isPmReviewGroupActive ? 'open' : '' ?>" data-nav-group="pm-review">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isPmReviewGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="pm-review"
                        aria-expanded="<?= $isPmReviewGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-pm-review"
                        title="Project Review">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('review', 18) ?></span>
                    <span class="bms-nav-label">Project Review</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-pm-review" role="region" aria-label="Project Review Submenu">
                    <a href="<?= url('/pm/projects?status=under_review') ?>"
                       class="bms-subnav-link <?= $isPmUnderReviewActive ? 'active' : '' ?>"
                       title="Projects Under Review">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Projects Under Review</span>
                    </a>
                    <a href="<?= url('/pm/projects?status=returned') ?>"
                       class="bms-subnav-link <?= $isPmReturnedActive ? 'active' : '' ?>"
                       title="Returned Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned Projects</span>
                    </a>
                </div>
            </div>

            <!-- PROJECT APPROVAL (Dropdown) -->
            <div class="bms-nav-group <?= $isPmApprovalGroupActive ? 'open' : '' ?>" data-nav-group="pm-approval">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isPmApprovalGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="pm-approval"
                        aria-expanded="<?= $isPmApprovalGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-pm-approval"
                        title="Project Approval">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('check-circle', 18) ?></span>
                    <span class="bms-nav-label">Project Approval</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-pm-approval" role="region" aria-label="Project Approval Submenu">
                    <a href="<?= url('/pm/projects?status=approved') ?>"
                       class="bms-subnav-link <?= $isPmApprovedActive ? 'active' : '' ?>"
                       title="Approved Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Approved Projects</span>
                    </a>
                </div>
            </div>

            <!-- PROJECT FORWARDING (Dropdown) -->
            <div class="bms-nav-group <?= $isPmForwardingGroupActive ? 'open' : '' ?>" data-nav-group="pm-forwarding">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isPmForwardingGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="pm-forwarding"
                        aria-expanded="<?= $isPmForwardingGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-pm-forwarding"
                        title="Project Forwarding">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('send', 18) ?></span>
                    <span class="bms-nav-label">Project Forwarding</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-pm-forwarding" role="region" aria-label="Project Forwarding Submenu">
                    <a href="<?= url('/pm/projects?status=ready_for_admin_finance') ?>"
                       class="bms-subnav-link <?= $isPmReadyForwardActive ? 'active' : '' ?>"
                       title="Ready for Admin Finance">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Ready for Admin Finance</span>
                    </a>
                    <a href="<?= url('/pm/projects?status=forwarded_to_admin_finance') ?>"
                       class="bms-subnav-link <?= $isPmForwardedActive ? 'active' : '' ?>"
                       title="Forwarded to Admin Finance">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Forwarded to Admin Finance</span>
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

            // 4. Operations Handover Submenu
            $isAfinReadyOpsActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['ready_for_operations', 'ready_ops'], true);
            $isAfinForwardedOpsActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['forwarded_to_operations', 'forwarded_ops', 'Forwarded to Operations'], true);
            $isAfinHandoverGroupActive = $isAfinReadyOpsActive || $isAfinForwardedOpsActive;

            // 5. Actual BOM Management Submenu
            $isAfinAbSubmittedActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['actual_bom_submitted', 'actual_bom_incoming', 'Actual BOM Submitted to Admin Finance'], true);
            $isAfinAbUnderReviewActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['actual_bom_under_review', 'Actual BOM Under Admin Finance Review'], true);
            $isAfinAbReturnedActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['actual_bom_returned', 'Actual BOM Returned for Corrections'], true);
            $isAfinAbAcceptedActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['actual_bom_accepted', 'Actual BOM Accepted by Admin Finance'], true);
            $isAfinActualBomGroupActive = $isAfinAbSubmittedActive || $isAfinAbUnderReviewActive || $isAfinAbReturnedActive || $isAfinAbAcceptedActive;

            // 6. Purchasing Management Submenu
            $isAfinPoRecordsActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['po_records', 'has_po'], true);
            $isAfinPoIssuedActive = ($afinActiveNav === 'afin_projects') && in_array($afinCurrentStatus, ['po_issued', 'Issued to Supplier'], true);
            $isAfinPurchasingGroupActive = $isAfinPoRecordsActive || $isAfinPoIssuedActive;
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

            <!-- OPERATIONS HANDOVER (Dropdown) -->
            <div class="bms-nav-group <?= $isAfinHandoverGroupActive ? 'open' : '' ?>" data-nav-group="afin-handover">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isAfinHandoverGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="afin-handover"
                        aria-expanded="<?= $isAfinHandoverGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-afin-handover"
                        title="Operations Handover">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('send', 18) ?></span>
                    <span class="bms-nav-label">Operations Handover</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-afin-handover" role="region" aria-label="Operations Handover Submenu">
                    <a href="<?= url('/admin-finance/projects?status=ready_for_operations') ?>"
                       class="bms-subnav-link <?= $isAfinReadyOpsActive ? 'active' : '' ?>"
                       title="Projects Ready for Operations">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Projects Ready for Operations</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=forwarded_to_operations') ?>"
                       class="bms-subnav-link <?= $isAfinForwardedOpsActive ? 'active' : '' ?>"
                       title="Forwarded Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Forwarded Projects</span>
                    </a>
                </div>
            </div>

            <!-- ACTUAL BOM MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isAfinActualBomGroupActive ? 'open' : '' ?>" data-nav-group="afin-actual-bom">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isAfinActualBomGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="afin-actual-bom"
                        aria-expanded="<?= $isAfinActualBomGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-afin-actual-bom"
                        title="Actual BOM Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('layers', 18) ?></span>
                    <span class="bms-nav-label">Actual BOM Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-afin-actual-bom" role="region" aria-label="Actual BOM Management Submenu">
                    <a href="<?= url('/admin-finance/projects?status=actual_bom_submitted') ?>"
                       class="bms-subnav-link <?= $isAfinAbSubmittedActive ? 'active' : '' ?>"
                       title="Incoming Actual BOM">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Incoming Actual BOM</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=actual_bom_under_review') ?>"
                       class="bms-subnav-link <?= $isAfinAbUnderReviewActive ? 'active' : '' ?>"
                       title="Actual BOM Under Review">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Actual BOM Under Review</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=actual_bom_returned') ?>"
                       class="bms-subnav-link <?= $isAfinAbReturnedActive ? 'active' : '' ?>"
                       title="Returned Actual BOM">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned Actual BOM</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=actual_bom_accepted') ?>"
                       class="bms-subnav-link <?= $isAfinAbAcceptedActive ? 'active' : '' ?>"
                       title="Accepted Actual BOM">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Accepted Actual BOM</span>
                    </a>
                </div>
            </div>

            <!-- PURCHASING MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isAfinPurchasingGroupActive ? 'open' : '' ?>" data-nav-group="afin-purchasing">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isAfinPurchasingGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="afin-purchasing"
                        aria-expanded="<?= $isAfinPurchasingGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-afin-purchasing"
                        title="Purchasing Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('briefcase', 18) ?></span>
                    <span class="bms-nav-label">Purchasing Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-afin-purchasing" role="region" aria-label="Purchasing Management Submenu">
                    <a href="<?= url('/admin-finance/projects?status=po_records') ?>"
                       class="bms-subnav-link <?= $isAfinPoRecordsActive ? 'active' : '' ?>"
                       title="Purchase Order Records">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Purchase Order Records</span>
                    </a>
                    <a href="<?= url('/admin-finance/projects?status=po_issued') ?>"
                       class="bms-subnav-link <?= $isAfinPoIssuedActive ? 'active' : '' ?>"
                       title="Purchase Order Tracking">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Purchase Order Tracking</span>
                    </a>
                </div>
            </div>
        <?php elseif (!empty($isOperationsUser)): ?>
            <?php
            // Resolve current operations filter states for deep-linking & auto-expansion
            $opsQueryStr = (string) ($_SERVER['QUERY_STRING'] ?? '');
            $opsQueryParsed = [];
            parse_str($opsQueryStr, $opsQueryParsed);
            $opsCurrentStatus = (string) ($opsQueryParsed['status'] ?? ($_GET['status'] ?? ''));
            $opsActiveNav = (string) ($activeNav ?? '');

            // 1. Dashboard Active State
            $isOpsDashActive = in_array($opsActiveNav, ['ops_dashboard', 'dashboard', 'dashboard_dept'], true);

            // 2. Project Management Submenu
            $isOpsReceivedActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === 'awaiting_pr');
            $isOpsAllProjectsActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === '');
            $isOpsPmGroupActive = $isOpsReceivedActive || $isOpsAllProjectsActive;

            // 3. Purchase Request Management Submenu
            $isOpsPrDraftActive = ($opsActiveNav === 'ops_projects') && in_array($opsCurrentStatus, ['draft', 'preparing_pr'], true);
            $isOpsPrPendingActive = ($opsActiveNav === 'ops_projects') && in_array($opsCurrentStatus, ['pending', 'submitted_pr'], true);
            $isOpsPrApprovedActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === 'approved');
            $isOpsPrReturnedActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === 'returned');
            $isOpsPrRejectedActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === 'rejected');
            $isOpsPrGroupActive = $isOpsPrDraftActive || $isOpsPrPendingActive || $isOpsPrApprovedActive || $isOpsPrReturnedActive || $isOpsPrRejectedActive;

            // 4. Purchasing Tracking Submenu
            $isOpsConvertedPoActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === 'converted_po');
            $isOpsAwaitingPurchasingActive = ($opsActiveNav === 'ops_projects') && ($opsCurrentStatus === 'awaiting_purchasing');
            $isOpsPurchasingGroupActive = $isOpsConvertedPoActive || $isOpsAwaitingPurchasingActive;
            ?>

            <!-- DASHBOARD -->
            <a href="<?= url('/operations/dashboard') ?>"
               class="bms-nav-link <?= $isOpsDashActive ? 'active' : '' ?>"
               title="Operations Dashboard">
                <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('dashboard', 18) ?></span>
                <span class="bms-nav-label">Operations Dashboard</span>
            </a>

            <!-- PROJECT MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isOpsPmGroupActive ? 'open' : '' ?>" data-nav-group="ops-pm">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpsPmGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="ops-pm"
                        aria-expanded="<?= $isOpsPmGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-ops-pm"
                        title="Project Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('folder', 18) ?></span>
                    <span class="bms-nav-label">Project Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-ops-pm" role="region" aria-label="Project Management Submenu">
                    <a href="<?= url('/operations/projects?status=awaiting_pr') ?>"
                       class="bms-subnav-link <?= $isOpsReceivedActive ? 'active' : '' ?>"
                       title="Received Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Received Projects</span>
                    </a>
                    <a href="<?= url('/operations/projects') ?>"
                       class="bms-subnav-link <?= $isOpsAllProjectsActive ? 'active' : '' ?>"
                       title="All Operations Projects">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">All Operations Projects</span>
                    </a>
                </div>
            </div>

            <!-- PURCHASE REQUEST MANAGEMENT (Dropdown) -->
            <div class="bms-nav-group <?= $isOpsPrGroupActive ? 'open' : '' ?>" data-nav-group="ops-pr">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpsPrGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="ops-pr"
                        aria-expanded="<?= $isOpsPrGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-ops-pr"
                        title="Purchase Request Management">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('file-text', 18) ?></span>
                    <span class="bms-nav-label">Purchase Request Management</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-ops-pr" role="region" aria-label="Purchase Request Management Submenu">
                    <a href="<?= url('/operations/projects?status=draft') ?>"
                       class="bms-subnav-link <?= $isOpsPrDraftActive ? 'active' : '' ?>"
                       title="Draft Purchase Requests">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Draft Purchase Requests</span>
                    </a>
                    <a href="<?= url('/operations/projects?status=pending') ?>"
                       class="bms-subnav-link <?= $isOpsPrPendingActive ? 'active' : '' ?>"
                       title="Submitted / Pending PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Submitted / Pending PRs</span>
                    </a>
                    <a href="<?= url('/operations/projects?status=approved') ?>"
                       class="bms-subnav-link <?= $isOpsPrApprovedActive ? 'active' : '' ?>"
                       title="Approved PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Approved PRs</span>
                    </a>
                    <a href="<?= url('/operations/projects?status=returned') ?>"
                       class="bms-subnav-link <?= $isOpsPrReturnedActive ? 'active' : '' ?>"
                       title="Returned PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Returned PRs</span>
                    </a>
                    <a href="<?= url('/operations/projects?status=rejected') ?>"
                       class="bms-subnav-link <?= $isOpsPrRejectedActive ? 'active' : '' ?>"
                       title="Rejected PRs">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Rejected PRs</span>
                    </a>
                </div>
            </div>

            <!-- PURCHASING TRACKING (Dropdown) -->
            <div class="bms-nav-group <?= $isOpsPurchasingGroupActive ? 'open' : '' ?>" data-nav-group="ops-purchasing">
                <button type="button"
                        class="bms-nav-dropdown-toggle <?= $isOpsPurchasingGroupActive ? 'active-parent' : '' ?>"
                        data-nav-toggle="ops-purchasing"
                        aria-expanded="<?= $isOpsPurchasingGroupActive ? 'true' : 'false' ?>"
                        aria-controls="nav-group-ops-purchasing"
                        title="Purchasing Tracking">
                    <span class="bms-nav-icon" aria-hidden="true"><?= bms_icon('briefcase', 18) ?></span>
                    <span class="bms-nav-label">Purchasing Tracking</span>
                    <span class="bms-nav-chevron" aria-hidden="true"><?= bms_icon('chevron-down', 14) ?></span>
                </button>
                <div class="bms-subnav-menu" id="nav-group-ops-purchasing" role="region" aria-label="Purchasing Tracking Submenu">
                    <a href="<?= url('/operations/projects?status=converted_po') ?>"
                       class="bms-subnav-link <?= $isOpsConvertedPoActive ? 'active' : '' ?>"
                       title="Purchase Order Tracking">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Purchase Order Tracking</span>
                    </a>
                    <a href="<?= url('/operations/projects?status=awaiting_purchasing') ?>"
                       class="bms-subnav-link <?= $isOpsAwaitingPurchasingActive ? 'active' : '' ?>"
                       title="Awaiting Purchasing">
                        <span class="bms-subnav-bullet" aria-hidden="true"></span>
                        <span class="bms-nav-label">Awaiting Purchasing</span>
                    </a>
                </div>
            </div>
        <?php elseif (!empty($isOpsFinanceUser)): ?>
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
