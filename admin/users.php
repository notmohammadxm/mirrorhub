<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/users.php');
    }

    if (isset($_POST['req_action']) && $_POST['req_action'] === 'delete') {
        if ($id == $_SESSION['user_id']) {
            flash('error', 'نمی‌توانید کاربر فعلی خود را حذف کنید.');
            redirect(SITE_URL . '/admin/users.php');
        }
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        flash('success', 'کاربر حذف شد.');
        redirect(SITE_URL . '/admin/users.php');
    }

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';

    if (empty($username)) {
        flash('error', 'نام کاربری الزامی است.');
        redirect(SITE_URL . '/admin/users.php?action=' . ($id ? "edit&id=$id" : 'add'));
    }

    try {
        if ($action === 'edit' && $id) {
            if ($id == $_SESSION['user_id'] && $role !== 'admin') {
                flash('error', 'نمی‌توانید نقش خود را از مدیر به کاربر تغییر دهید.');
                redirect(SITE_URL . '/admin/users.php?action=edit&id=' . $id);
            }

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET username=?, password_hash=?, role=? WHERE id=?")
                    ->execute([$username, $hash, $role, $id]);
            } else {
                $pdo->prepare("UPDATE users SET username=?, role=? WHERE id=?")
                    ->execute([$username, $role, $id]);
            }
            flash('success', 'کاربر با موفقیت ویرایش شد.');
        } else {
            if (empty($password)) {
                flash('error', 'رمز عبور الزامی است.');
                redirect(SITE_URL . '/admin/users.php?action=add');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
                ->execute([$username, $hash, $role]);
            flash('success', 'کاربر با موفقیت اضافه شد.');
        }
        redirect(SITE_URL . '/admin/users.php');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            flash('error', 'این نام کاربری قبلاً ثبت شده است.');
        } else {
            flash('error', 'خطا در ذخیره‌سازی: ' . $e->getMessage());
        }
        redirect(SITE_URL . '/admin/users.php');
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$currentUser = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $currentUser = $stmt->fetch();
    if (!$currentUser) {
        flash('error', 'کاربر مورد نظر یافت نشد.');
        redirect(SITE_URL . '/admin/users.php');
    }
}

renderHeader('مدیریت کاربران', true);
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">مدیریت کاربران</h1>
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline">بازگشت به لیست</a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/admin/users.php?action=add" class="btn btn-primary">افزودن کاربر جدید</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="form-container card">
            <h2><?= $action === 'edit' ? 'ویرایش کاربر' : 'افزودن کاربر جدید' ?></h2>
            <form action="<?= SITE_URL ?>/admin/users.php?action=<?= $action ?>&id=<?= $id ?>" method="POST">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                
                <div class="form-group">
                    <label for="username">نام کاربری <span class="required">*</span></label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" required dir="ltr" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">رمز عبور <?= $action === 'edit' ? '(خالی بگذارید تا تغییر نکند)' : '<span class="required">*</span>' ?></label>
                    <input type="password" id="password" name="password" <?= $action === 'add' ? 'required' : '' ?> dir="ltr" autocomplete="new-password" placeholder="<?= $action === 'edit' ? '••••••••' : '' ?>">
                </div>
                <div class="form-group">
                    <label for="role">نقش</label>
                    <select id="role" name="role">
                        <option value="user" <?= ($currentUser['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>کاربر عادی</option>
                        <option value="admin" <?= ($currentUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>مدیر</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline">انصراف</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>نام کاربری</th>
                        <th>نقش</th>
                        <th>تاریخ ثبت‌نام</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="4" class="empty-state">کاربری یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="ltr-text">
                                    <?= htmlspecialchars($user['username']) ?>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge badge-primary">شما</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-<?= $user['role'] === 'admin' ? 'primary' : 'default' ?>"><?= $user['role'] === 'admin' ? 'مدیر' : 'کاربر' ?></span></td>
                                <td><?= formatDate($user['created_at']) ?></td>
                                <td class="actions">
                                    <a href="?action=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline">ویرایش</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form action="?id=<?= $user['id'] ?>" method="POST" class="inline-form" onsubmit="return confirm('آیا از حذف مطمئن هستید؟');">
                                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="req_action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php renderFooter(); ?>