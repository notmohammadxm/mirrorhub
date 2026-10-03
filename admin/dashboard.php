<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

requireAdmin();

$stats = [
    'mirrors' => (int)$pdo->query("SELECT COUNT(*) FROM mirrors")->fetchColumn(),
    'categories' => (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'pending_requests' => (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn(),
];

$visits = getVisitStats($pdo);

$stmt = $pdo->query("SELECT * FROM requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
$recentRequests = $stmt->fetchAll();

renderHeader('داشبورد مدیریت', true);
?>

<section class="section">
    <h1 class="section-title">داشبورد</h1>
    <p class="section-desc">خوش آمدید، <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>. خلاصه‌ای از وضعیت سیستم:</p>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon"><?= icon('server', 20) ?></span>
            <h3><?= number_format($stats['mirrors']) ?></h3>
            <p>میرورهای ثبت شده</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('folder', 20) ?></span>
            <h3><?= number_format($stats['categories']) ?></h3>
            <p>دسته‌بندی‌ها</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('users', 20) ?></span>
            <h3><?= number_format($stats['users']) ?></h3>
            <p>کاربران سیستم</p>
        </div>
        <div class="stat-card highlight">
            <span class="stat-icon"><?= icon('inbox', 20) ?></span>
            <h3><?= number_format($stats['pending_requests']) ?></h3>
            <p>درخواست‌های بررسی‌نشده</p>
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">آمار بازدید سایت</h2>
    <div class="stats-grid">
        <div class="stat-card info">
            <span class="stat-icon"><?= icon('eye', 20) ?></span>
            <h3><?= number_format($visits['today']) ?></h3>
            <p>بازدید امروز</p>
            <span class="stat-sub">یکتا: <?= number_format($visits['uniqueToday']) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('activity', 20) ?></span>
            <h3><?= number_format($visits['yesterday']) ?></h3>
            <p>دیروز</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('activity', 20) ?></span>
            <h3><?= number_format($visits['week']) ?></h3>
            <p>۷ روز اخیر</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('activity', 20) ?></span>
            <h3><?= number_format($visits['month']) ?></h3>
            <p>۳۰ روز اخیر</p>
        </div>
        <div class="stat-card info">
            <span class="stat-icon"><?= icon('eye', 20) ?></span>
            <h3><?= number_format($visits['total']) ?></h3>
            <p>کل بازدیدها</p>
            <span class="stat-sub">بازدیدکننده یکتا: <?= number_format($visits['unique']) ?></span>
        </div>
    </div>
</section>

<?php if (!empty($recentRequests)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">آخرین درخواست‌های بررسی‌نشده</h2>
        <a href="<?= SITE_URL ?>/admin/requests.php" class="btn btn-sm btn-outline">مشاهده همه</a>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>نام میرور</th>
                    <th>آدرس</th>
                    <th>دسته‌بندی پیشنهادی</th>
                    <th>تاریخ ثبت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRequests as $req): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($req['name_fa']) ?>
                            <?php if (!empty($req['name_en'])): ?>
                                <br><span class="ltr-text" style="font-size:0.75rem;"><?= htmlspecialchars($req['name_en']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="ltr-text"><?= htmlspecialchars(excerpt($req['url'], 40)) ?></td>
                        <td><?= htmlspecialchars($req['category_name']) ?></td>
                        <td><?= formatDate($req['created_at']) ?></td>
                        <td>
                            <a href="<?= SITE_URL ?>/admin/requests.php" class="btn btn-sm btn-primary">بررسی</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php renderFooter(); ?>