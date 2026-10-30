<?php
$isSidebarCollapsed = (($_COOKIE['bms_sidebar_collapsed'] ?? '') === '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($appName ?? 'Business Management System') ?></title>
    <link rel="icon" type="image/png" href="<?= asset('images/pagecom-logo.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="<?= $isSidebarCollapsed ? 'sidebar-collapsed' : '' ?>">
    <div class="bms-shell">
        <?php require BASE_PATH . '/app/Views/layouts/partials/sidebar.php'; ?>
        <div class="bms-sidebar-backdrop" id="bmsSidebarBackdrop"></div>

        <div class="bms-main">
            <?php require BASE_PATH . '/app/Views/layouts/partials/topbar.php'; ?>

            <main class="bms-content">
                <div class="container-fluid px-0">
                    <?php require BASE_PATH . '/app/Views/layouts/partials/alerts.php'; ?>
                    <?= $content ?>
                </div>
            </main>

            <footer class="bms-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><?= e($appName ?? 'SYB Business Management System') ?> &bull; Integrated Enterprise Foundation</span>
                <span>Shared 6-Department Platform</span>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>

