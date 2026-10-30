<?php
$userFullName = trim((string) ($currentUser['full_name'] ?? 'User'));
$userInitial = strtoupper((string) substr($userFullName !== '' ? $userFullName : 'U', 0, 1));
?>
<aside class="bms-sidebar" id="bmsSidebar" aria-label="Primary Navigation">
    <div class="bms-sidebar-header">
        <a href="<?= url('/dashboard') ?>" class="bms-sidebar-brand" title="PAGECOM INC. — Business Management System">
            <img src="<?= asset('images/pagecom-logo.png') ?>" alt="PAGECOM INC." class="bms-brand-logo">
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
            <a href="<?= url('/marketing/dashboard') ?>"
               class="bms-nav-link <?= ($activeNav ?? '') === 'marketing_dashboard' ? 'active' : '' ?>"
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

            <a href="<?= url('/marketing/projects') ?>"
               class="bms-nav-link <?= in_array(($activeNav ?? ''), ['marketing_projects', 'projects'], true) ? 'active' : '' ?>"
               title="Projects">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Projects</span>
            </a>
        <?php elseif (!empty($isProjectManagementUser)): ?>
            <a href="<?= url('/pm/dashboard') ?>"
               class="bms-nav-link <?= in_array(($activeNav ?? ''), ['pm_dashboard', 'dashboard', 'dashboard_dept'], true) ? 'active' : '' ?>"
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

            <a href="<?= url('/pm/projects') ?>"
               class="bms-nav-link <?= in_array(($activeNav ?? ''), ['pm_projects', 'projects'], true) ? 'active' : '' ?>"
               title="Projects">
                <span class="bms-nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </span>
                <span class="bms-nav-label">Projects</span>
            </a>
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

        <?php if (!empty($canRegisterProject)): ?>
            <a href="<?= url(!empty($isMarketingUser) ? '/marketing/projects/create' : '/projects/create') ?>"
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
        <div class="bms-sidebar-user p-3 border-top border-secondary border-opacity-25 small"
             title="<?= e($userFullName) ?> (<?= e($currentUser['role_name'] ?? '') ?><?= !empty($currentUser['department_code']) ? ' • ' . e($currentUser['department_code']) : '' ?>)">
            <div class="bms-user-initials" aria-hidden="true"><?= e($userInitial) ?></div>
            <div class="bms-user-meta min-w-0">
                <div class="text-white fw-semibold text-truncate"><?= e($userFullName) ?></div>
                <div class="text-secondary text-truncate">
                    <?= e($currentUser['role_name']) ?>
                    <?php if (!empty($currentUser['department_code'])): ?>
                        &bull; <?= e($currentUser['department_code']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</aside>
