<?php
function renderHeader($title = '', $isAdmin = false) {
    if (!$isAdmin) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            recordVisit($pdo);
        }
    }

    $siteName = SITE_NAME;
    $pageTitle = $title ? "$title | $siteName" : $siteName;
    $flashSuccess = flash('success');
    $flashError = flash('error');
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($siteName) ?> - مخزن میرورهای نرم‌افزاری">
    <meta name="theme-color" content="#0e1410">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='40' fill='%23a3d65c'/%3E%3C/svg%3E">
</head>
<body class="<?= $isAdmin ? 'admin-layout' : 'public-layout' ?>">
    <header class="main-header">
        <div class="container">
            <a href="<?= SITE_URL ?>/<?= $isAdmin ? 'admin/dashboard.php' : 'index.php' ?>" class="logo"><?= htmlspecialchars($siteName) ?></a>
            <nav class="main-nav">
                <?php if ($isAdmin): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                        <?= icon('dashboard', 15) ?><span>داشبورد</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/categories.php" class="<?= $currentPage === 'categories.php' ? 'active' : '' ?>">
                        <?= icon('folder', 15) ?><span>دسته‌بندی‌ها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/mirrors.php" class="<?= $currentPage === 'mirrors.php' ? 'active' : '' ?>">
                        <?= icon('server', 15) ?><span>میرورها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/requests.php" class="<?= $currentPage === 'requests.php' ? 'active' : '' ?>">
                        <?= icon('inbox', 15) ?><span>درخواست‌ها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">
                        <?= icon('users', 15) ?><span>کاربران</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/login.php?action=logout" class="btn-logout" title="خروج از حساب">
                        <?= icon('log-out', 14) ?><span>خروج</span>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <?= icon('home', 15) ?><span>خانه</span>
                    </a>
                    <a href="<?= SITE_URL ?>/search.php" class="<?= $currentPage === 'search.php' ? 'active' : '' ?>">
                        <?= icon('search', 15) ?><span>جستجو</span>
                    </a>
                    <a href="<?= SITE_URL ?>/suggest.php" class="<?= $currentPage === 'suggest.php' ? 'active' : '' ?>">
                        <?= icon('plus-circle', 15) ?><span>پیشنهاد میرور</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
<?php
}

function renderFooter() {
?>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. تمامی حقوق محفوظ است.</p>
            <p class="footer-made">ساخته شده با <span class="heart"><?= icon('heart', 14) ?></span> در ایران</p>
        </div>
    </footer>

    <script>window.MH_SITE_URL = <?= json_encode(SITE_URL) ?>;</script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php
}