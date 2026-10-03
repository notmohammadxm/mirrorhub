<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/requests.php');
    }

    $reqAction = $_POST['req_action'] ?? '';
    $adminNote = sanitize($_POST['admin_note'] ?? '');

    if ($reqAction === 'mark_reviewed') {
        $pdo->prepare("UPDATE requests SET status='reviewed', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$adminNote, (int)$_SESSION['user_id'], $id]);
        flash('success', 'درخواست به عنوان بررسی‌شده علامت خورد.');

    } elseif ($reqAction === 'mark_pending') {
        $pdo->prepare("UPDATE requests SET status='pending', admin_note=?, reviewed_by=NULL, reviewed_at=NULL WHERE id=?")
            ->execute([$adminNote, $id]);
        flash('success', 'یادداشت ذخیره شد. درخواست در حالت بررسی‌نشده باقی ماند.');

    } elseif ($reqAction === 'delete') {
        $pdo->prepare("DELETE FROM requests WHERE id = ?")->execute([$id]);
        flash('success', 'درخواست حذف شد.');
    }

    redirect(SITE_URL . '/admin/requests.php');
}

$statusFilter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'reviewed'];
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'all';

$where = '';
$params = [];
if ($statusFilter !== 'all') {
    $where = "WHERE status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT * FROM requests $where ORDER BY created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll();
foreach ($requests as &$req) {
    $req['protocols'] = getRequestProtocols($pdo, $req['id']);
    $req['links'] = getRequestLinks($pdo, $req['id']);
    if (empty($req['links']) && !empty($req['url'])) {
        $req['links'] = [['url' => $req['url']]];
    }
}
unset($req);

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();

$csrf = generateCsrfToken();

renderHeader('مدیریت درخواست‌ها', true);
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">مدیریت درخواست‌ها</h1>
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-warning"><?= $pendingCount ?> درخواست بررسی‌نشده</span>
        <?php endif; ?>
    </div>

    <div class="filters" style="margin-bottom:1.25rem;">
        <a href="?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">همه</a>
        <a href="?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline' ?>">بررسی‌نشده</a>
        <a href="?status=reviewed" class="btn btn-sm <?= $statusFilter === 'reviewed' ? 'btn-primary' : 'btn-outline' ?>">بررسی‌شده</a>
    </div>

    <div class="table-container card">
        <table class="data-table requests-table">
            <thead>
                <tr>
                    <th>نام میرور</th>
                    <th>لینک‌ها</th>
                    <th>تماس</th>
                    <th>پروتکل</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="7" class="empty-state">درخواستی یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td data-label="نام میرور">
                                <span class="req-name"><?= htmlspecialchars($req['name_fa']) ?></span>
                                <?php if (!empty($req['name_en'])): ?>
                                    <span class="req-name-en"><?= htmlspecialchars($req['name_en']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="لینک‌ها" class="ltr-text">
                                <div class="request-links-summary">
                                    <?php foreach (array_slice($req['links'], 0, 2) as $link): ?>
                                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(excerpt($link['url'], 34)) ?></a>
                                    <?php endforeach; ?>
                                    <?php if (count($req['links']) > 2): ?>
                                        <span class="badge badge-default">+<?= count($req['links']) - 2 ?> لینک دیگر</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="تماس">
                                <div class="request-contact-summary">
                                    <a href="tel:<?= htmlspecialchars($req['phone'] ?? '') ?>" dir="ltr"><?= htmlspecialchars($req['phone'] ?? '-') ?></a>
                                    <?php if (!empty($req['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($req['email']) ?>" dir="ltr"><?= htmlspecialchars($req['email']) ?></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="پروتکل">
                                <?= protocolBadges($req['protocols']) ?>
                            </td>
                            <td data-label="وضعیت">
                                <span class="badge badge-<?= $req['status'] === 'reviewed' ? 'success' : 'warning' ?>">
                                    <?= $req['status'] === 'reviewed' ? 'بررسی‌شده' : 'بررسی‌نشده' ?>
                                </span>
                            </td>
                            <td data-label="تاریخ"><?= formatDate($req['created_at']) ?></td>
                            <td data-label="عملیات" class="actions">
                                <button type="button"
                                    class="btn btn-sm btn-primary"
                                    data-review-request
                                    data-id="<?= $req['id'] ?>"
                                    data-name-fa="<?= htmlspecialchars($req['name_fa']) ?>"
                                    data-name-en="<?= htmlspecialchars($req['name_en'] ?? '') ?>"
                                    data-url="<?= htmlspecialchars($req['url']) ?>"
                                    data-links="<?= htmlspecialchars(json_encode($req['links'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
                                    data-protocols="<?= htmlspecialchars(json_encode($req['protocols'], JSON_UNESCAPED_UNICODE)) ?>"
                                    data-phone="<?= htmlspecialchars($req['phone'] ?? '') ?>"
                                    data-email="<?= htmlspecialchars($req['email'] ?? '') ?>"
                                    data-description="<?= htmlspecialchars($req['description'] ?? '') ?>"
                                    data-status="<?= htmlspecialchars($req['status']) ?>"
                                    data-note="<?= htmlspecialchars($req['admin_note'] ?? '') ?>"
                                    data-mirror-id="<?= (int)($req['mirror_id'] ?? 0) ?>"
                                    data-csrf="<?= htmlspecialchars($csrf) ?>">
                                    رسیدگی
                                </button>
                                <form action="?id=<?= $req['id'] ?>" method="POST" class="inline-form" data-confirm="آیا از حذف این درخواست مطمئن هستید؟">
                                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrf ?>">
                                    <input type="hidden" name="req_action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php renderFooter(); ?>