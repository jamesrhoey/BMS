<?php
$unreadCount = (int) ($unreadNotificationCount ?? 0);
$recentAlerts = is_array($topbarNotifications ?? null) ? $topbarNotifications : [];
?>
<header class="bms-topbar">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="bmsSidebarToggle" aria-label="Toggle navigation">
            &#9776;
        </button>
        <div>
            <h1 class="h5 mb-0 fw-semibold"><?= e($pageTitle ?? 'Dashboard') ?></h1>
        </div>
    </div>

    <?php if (!empty($currentUser)): ?>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown bms-notif-wrapper">
                <button type="button"
                        class="bms-topbar-icon-btn position-relative"
                        id="bmsNotifDropdownBtn"
                        data-bs-toggle="dropdown"
                        data-topbar-dropdown="bmsNotifDropdownMenu"
                        aria-expanded="false"
                        title="Alerts"
                        aria-label="Alerts<?= $unreadCount > 0 ? ' (' . $unreadCount . ' unread)' : '' ?>">
                    <?= bms_icon('bell', 18) ?>
                    <?php if ($unreadCount > 0): ?>
                        <span class="bms-topbar-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                    <?php endif; ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end bms-notif-dropdown p-0" id="bmsNotifDropdownMenu" aria-labelledby="bmsNotifDropdownBtn">
                    <div class="bms-notif-dropdown-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold text-dark small">Alerts</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge rounded-pill bg-danger"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" action="<?= url('/notifications/read-all') ?>" class="m-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none small fw-medium">Mark all read</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="bms-notif-dropdown-body">
                        <?php if ($recentAlerts === []): ?>
                            <div class="px-3 py-4 text-center text-secondary small">
                                No alerts at the moment.
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentAlerts as $alertItem): ?>
                                <?php $alertUnread = (int) ($alertItem['is_read'] ?? 0) === 0; ?>
                                <div class="bms-notif-item <?= $alertUnread ? 'is-unread' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="min-w-0">
                                            <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                                                <?php if ($alertUnread): ?>
                                                    <span class="bms-notif-dot" aria-hidden="true"></span>
                                                <?php endif; ?>
                                                <?php if (!empty($alertItem['project_code'])): ?>
                                                    <span class="badge badge-soft-primary font-monospace"><?= e($alertItem['project_code']) ?></span>
                                                <?php endif; ?>
                                                <span class="fw-semibold text-dark small"><?= e($alertItem['title']) ?></span>
                                            </div>
                                            <div class="text-secondary small text-break mb-1"><?= e($alertItem['message']) ?></div>
                                            <div class="bms-notif-time"><?= format_datetime($alertItem['created_at']) ?></div>
                                        </div>

                                        <?php if (!empty($alertItem['action_url'])): ?>
                                            <form method="POST" action="<?= url('/notifications/' . (int) $alertItem['id'] . '/read') ?>" class="m-0 flex-shrink-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_to" value="<?= e((string) $alertItem['action_url']) ?>">
                                                <button type="submit" class="bms-action-icon bms-action-icon--primary" title="Open Project" aria-label="Open Project">
                                                    <?= bms_icon('eye', 16) ?>
                                                </button>
                                            </form>
                                        <?php elseif ($alertUnread): ?>
                                            <form method="POST" action="<?= url('/notifications/' . (int) $alertItem['id'] . '/read') ?>" class="m-0 flex-shrink-0">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="bms-action-icon bms-action-icon--success" title="Mark Read" aria-label="Mark Read">
                                                    <?= bms_icon('activate', 16) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <a href="<?= url('/notifications') ?>" class="bms-notif-dropdown-footer">
                        View All Notifications
                    </a>
                </div>
            </div>

            <div class="dropdown bms-user-wrapper">
                <button type="button"
                        class="bms-topbar-icon-btn"
                        id="bmsUserDropdownBtn"
                        data-bs-toggle="dropdown"
                        data-topbar-dropdown="bmsUserDropdownMenu"
                        aria-expanded="false"
                        title="<?= e($currentUser['username'] ?? 'Account') ?>"
                        aria-label="Account menu">
                    <?= bms_icon('user', 18) ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end bms-user-dropdown p-0" id="bmsUserDropdownMenu" aria-labelledby="bmsUserDropdownBtn">
                    <div class="bms-user-dropdown-header">
                        <div class="fw-semibold text-dark small text-truncate"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></div>
                        <div class="text-secondary text-truncate" style="font-size: 0.74rem;">
                            @<?= e($currentUser['username']) ?>
                            <?php if (!empty($currentUser['role_name'])): ?>
                                &bull; <?= e($currentUser['role_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-1">
                        <a href="<?= url('/profile') ?>" class="bms-user-dropdown-item">
                            <?= bms_icon('user', 16) ?>
                            <span>Profile</span>
                        </a>

                        <form method="POST" action="<?= url('/logout') ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="bms-user-dropdown-item bms-user-dropdown-item--danger">
                                <?= bms_icon('logout', 16) ?>
                                <span>Sign Out</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</header>

