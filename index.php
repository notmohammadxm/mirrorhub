<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

$topCategories = getCategories($pdo, null);

$stmt = $pdo->query("SELECT * FROM mirrors WHERE status = 'active' ORDER BY created_at DESC LIMIT 12");
$recentMirrors = $stmt->fetchAll();
foreach ($recentMirrors as &$mirror) {
    $mirror['categories'] = getMirrorCategories($pdo, $mirror['id']);
}
unset($mirror);

renderHeader('خانه');
?>

<section class="hero">
    <h1>مخزن <span>میرورهای نرم‌افزاری</span></h1>
    <p>دسترسی سریع و آسان به آخرین نسخه‌های نرم‌افزارها و بسته‌ها</p>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form">
        <input type="text" name="q" placeholder="جستجوی میرور..." required autocomplete="off">
        <button type="submit">جستجو</button>
    </form>
</section>

<section class="section">
    <div class="section-header">
        <h2 class="section-title">دسته‌بندی‌ها</h2>
        <span class="badge badge-primary"><?= count($topCategories) ?> دسته</span>
    </div>
    <div class="grid grid-cols-3">
        <?php if (empty($topCategories)): ?>
            <p class="empty-state">هنوز دسته‌بندی‌ای ایجاد نشده است.</p>
        <?php else: ?>
            <?php foreach ($topCategories as $cat): ?>
                <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($cat['slug']) ?>" class="card category-card">
                    <h3><?= htmlspecialchars($cat['name']) ?></h3>
                    <?php if ($cat['description']): ?>
                        <p><?= htmlspecialchars(excerpt($cat['description'], 80)) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($recentMirrors)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">آخرین میرورهای اضافه شده</h2>
        <a href="<?= SITE_URL ?>/search.php" class="btn btn-sm btn-outline">مشاهده همه</a>
    </div>
    <div class="grid grid-cols-2">
        <?php foreach ($recentMirrors as $mirror): ?>
            <?php $names = mirrorDisplayName($mirror); ?>
            <div class="card mirror-card">
                <div class="mirror-header">
                    <div class="mirror-titles">
                        <h3><?= htmlspecialchars($names['fa']) ?></h3>
                        <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                            <span class="mirror-name-en"><?= htmlspecialchars($names['en']) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= htmlspecialchars($mirror['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">مشاهده</a>
                </div>
                <?php if ($mirror['description']): ?>
                    <p class="mirror-desc"><?= htmlspecialchars(excerpt($mirror['description'], 120)) ?></p>
                <?php endif; ?>
                <div class="mirror-meta">
                    <span class="badge <?= getProtocolBadgeClass($mirror['protocol'] ?? 'https') ?> protocol-badge"><?= htmlspecialchars(strtoupper(getProtocolLabel($mirror['protocol'] ?? 'https'))) ?></span>
                    <?php foreach ($mirror['categories'] as $c): ?>
                        <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="badge badge-primary"><?= htmlspecialchars($c['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php renderFooter(); ?>