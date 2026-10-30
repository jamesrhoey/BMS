<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">Workflow Notifications</h2>
        <p class="text-secondary mb-0 small">Departmental handoffs, document verification submissions, and correction alerts.</p>
    </div>
    <?php if (!empty($unreadNotificationCount) && (int) $unreadNotificationCount > 0): ?>
        <form method="POST" action="<?= url('/notifications/read-all') ?>" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">Mark All as Read (<?= (int) $unreadNotificationCount ?>)</button>
        </form>
    <?php endif; ?>
</div>

<div class="bms-card">
    <div class="list-group list-group-flush">
        <?php if ($notifications === []): ?>
            <div class="p-5 text-center text-secondary">
                No workflow notifications have been sent to your account yet.
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <?php $isUnread = (int) ($notif['is_read'] ?? 0) === 0; ?>
                <div class="list-group-item px-4 py-3 <?= $isUnread ? 'bg-light' : '' ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div class="pe-2" style="max-width: 780px;">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <?php if ($isUnread): ?>
                                    <span class="badge bg-primary">Unread</span>
                                <?php else: ?>
                                    <span class="badge badge-soft-secondary">Read</span>
                                <?php endif; ?>

                                <?php if (!empty($notif['project_code'])): ?>
                                    <span class="badge badge-soft-primary font-monospace"><?= e($notif['project_code']) ?></span>
                                <?php endif; ?>

                                <strong class="text-dark"><?= e($notif['title']) ?></strong>
                            </div>
                            <div class="small text-secondary mb-1"><?= e($notif['message']) ?></div>
                            <div class="text-secondary" style="font-size: 0.75rem;">
                                <?= format_datetime($notif['created_at']) ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($notif['action_url'])): ?>
                                <form method="POST" action="<?= url('/notifications/' . (int) $notif['id'] . '/read') ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="<?= e((string) $notif['action_url']) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Open Project</button>
                                </form>
                            <?php elseif ($isUnread): ?>
                                <form method="POST" action="<?= url('/notifications/' . (int) $notif['id'] . '/read') ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Mark Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

