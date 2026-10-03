<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$fromRequest = (int)($_GET['from_request'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/mirrors.php');
    }

    if (isset($_POST['req_action']) && $_POST['req_action'] === 'delete') {
        $pdo->prepare("DELETE FROM mirrors WHERE id = ?")->execute([$id]);
        flash('success', 'میرور حذف شد.');
        redirect(SITE_URL . '/admin/mirrors.php');
    }

    $name_fa = sanitize($_POST['name_fa'] ?? '');
    $name_en = sanitize($_POST['name_en'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $url = filter_var(trim($_POST['url'] ?? ''), FILTER_SANITIZE_URL);
    $protocols = normalizeProtocols($_POST['protocols'] ?? [], $_POST['custom_protocol'] ?? '');
    $protocol = $protocols[0]['protocol'] ?? 'https';
    $description = sanitize($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $category_ids = array_filter(array_map('intval', $_POST['category_ids'] ?? []), fn($v) => $v > 0);
    $linkRequestId = (int)($_POST['link_request_id'] ?? 0);

    $backUrl = SITE_URL . '/admin/mirrors.php?action=' . ($id ? "edit&id=$id" : 'add') . ($linkRequestId ? "&from_request=$linkRequestId" : '');

    if (empty($name_fa) || empty($url) || empty($category_ids) || empty($protocols)) {
        flash('error', 'لطفاً فیلدهای الزامی را پر کنید و حداقل یک دسته‌بندی انتخاب کنید.');
        redirect($backUrl);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'آدرس اینترنتی وارد شده معتبر نیست.');
        redirect($backUrl);
    }

    if (empty($slug)) {
        $slug = slugify($name_en ?: $name_fa);
    }

    try {
        if ($action === 'edit' && $id) {
            $pdo->prepare("UPDATE mirrors SET name_fa=?, name_en=?, slug=?, url=?, protocol=?, description=?, status=? WHERE id=?")
                ->execute([$name_fa, $name_en, $slug, $url, $protocol, $description, $status, $id]);
            setMirrorCategories($pdo, $id, $category_ids);
            setMirrorProtocols($pdo, $id, $protocols);
            flash('success', 'میرور با موفقیت ویرایش شد.');
        } else {
            $pdo->prepare("INSERT INTO mirrors (name_fa, name_en, slug, url, protocol, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$name_fa, $name_en, $slug, $url, $protocol, $description, $status]);
            $newId = (int)$pdo->lastInsertId();
            setMirrorCategories($pdo, $newId, $category_ids);
            setMirrorProtocols($pdo, $newId, $protocols);

            if ($linkRequestId > 0) {
                $pdo->prepare("UPDATE requests SET mirror_id = ?, status='reviewed', reviewed_by=?, reviewed_at=NOW() WHERE id = ?")
                    ->execute([$newId, (int)$_SESSION['user_id'], $linkRequestId]);
                flash('success', 'میرور ساخته شد و به درخواست مرتبط متصل شد.');
            } else {
                flash('success', 'میرور با موفقیت اضافه شد.');
            }
        }
        redirect(SITE_URL . '/admin/mirrors.php');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            flash('error', 'این نامک (slug) قبلاً استفاده شده است.');
        } else {
            flash('error', 'خطا در ذخیره‌سازی: ' . $e->getMessage());
        }
        redirect(SITE_URL . '/admin/mirrors.php');
    }
}

$mirrors = $pdo->query("SELECT * FROM mirrors ORDER BY created_at DESC")->fetchAll();
foreach ($mirrors as &$m) {
    $m['categories'] = getMirrorCategories($pdo, $m['id']);
}
unset($m);

$categories = getAllCategories($pdo);
$currentMirror = null;
$currentCatIds = [];
if ($action === 'edit' && $id) {
    $currentMirror = getMirrorById($pdo, $id);
    if (!$currentMirror) {
        flash('error', 'میرور مورد نظر یافت نشد.');
        redirect(SITE_URL . '/admin/mirrors.php');
    }
    $currentCatIds = array_map(fn($c) => (int)$c['id'], getMirrorCategories($pdo, $id));
    $currentProtocols = getMirrorProtocols($pdo, $id);
}

$prefill = [];
$prefillRequestId = 0;
$prefillProtocols = [];
if ($action === 'add' && $fromRequest > 0) {
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->execute([$fromRequest]);
    $row = $stmt->fetch();
    if ($row) {
        $prefill = $row;
        $prefillRequestId = (int)$row['id'];
    }
}

$protocolOptions = getProtocolOptions();
$currentProtocols = $currentProtocols ?? [];

renderHeader('مدیریت میرورها', true);
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">مدیریت میرورها</h1>
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">بازگشت به لیست</a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/admin/mirrors.php?action=add" class="btn btn-primary">افزودن میرور جدید</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php
            $categoryGroups = [];
            foreach ($categories as $cat) {
                $pid = $cat['parent_id'] ? (int)$cat['parent_id'] : 0;
                if (!isset($categoryGroups[$pid])) $categoryGroups[$pid] = [];
                $categoryGroups[$pid][] = $cat;
            }
            $selectedProtocols = $currentProtocols;
            if (empty($selectedProtocols) && !empty($prefillProtocols)) {
                $selectedProtocols = $prefillProtocols;
            } elseif (empty($selectedProtocols) && !empty($prefill['protocol'])) {
                $selectedProtocols = normalizeProtocols([$prefill['protocol']]);
            }
        ?>
        <div class="mirror-editor">
            <div class="editor-head">
                <div class="editor-head-copy">
                    <span class="eyebrow">MirrorHub • مدیریت محتوا</span>
                    <h2><?= $action === 'edit' ? 'ویرایش میرور' : 'افزودن میرور جدید' ?></h2>
                    <p>اطلاعات، دسترسی‌ها و دسته‌بندی میرور را یک‌جا و با کنترل کامل مدیریت کنید.</p>
                </div>
                <span class="editor-badge"><?= $action === 'edit' ? 'ویرایش' : 'میرور جدید' ?></span>
            </div>

            <?php if ($prefillRequestId): ?>
                <div class="alert alert-success" style="margin-bottom:1rem;">
                    درخواست #<?= $prefillRequestId ?> به این فرم متصل است؛ اطلاعات درخواست تا حد ممکن از قبل وارد شده‌اند.
                </div>
            <?php endif; ?>

            <form action="<?= SITE_URL ?>/admin/mirrors.php?action=<?= $action ?>&id=<?= $id ?>" method="POST" class="smart-form card" data-require-category>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                <?php if ($prefillRequestId): ?><input type="hidden" name="link_request_id" value="<?= $prefillRequestId ?>"><?php endif; ?>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="step-badge">۱</span>
                        <div><h2>مشخصات پایه</h2><p>نام، آدرس و نامک عمومی میرور.</p></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label for="name_fa">نام فارسی <span class="required">*</span></label>
                            <input type="text" id="name_fa" name="name_fa" value="<?= htmlspecialchars($currentMirror['name_fa'] ?? $prefill['name_fa'] ?? '') ?>" required autocomplete="off" placeholder="مثلاً میرور اوبونتو">
                        </div>
                        <div class="form-group flex-1">
                            <label for="name_en">نام انگلیسی</label>
                            <input type="text" id="name_en" name="name_en" value="<?= htmlspecialchars($currentMirror['name_en'] ?? $prefill['name_en'] ?? '') ?>" dir="ltr" autocomplete="off" placeholder="Ubuntu Mirror">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label for="url">آدرس اینترنتی <span class="required">*</span></label>
                            <div class="input-with-prefix"><span>URL</span><input type="url" id="url" name="url" value="<?= htmlspecialchars($currentMirror['url'] ?? $prefill['url'] ?? '') ?>" dir="ltr" required placeholder="https://example.com/mirror" autocomplete="off"></div>
                        </div>
                        <div class="form-group flex-1">
                            <label for="slug">نامک (Slug)</label>
                            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($currentMirror['slug'] ?? '') ?>" dir="ltr" placeholder="خودکار" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="step-badge">۲</span>
                        <div><h2>روش‌های دسترسی</h2><p>یک یا چند پروتکل را فعال کنید.</p></div>
                    </div>
                    <div class="protocol-picker protocol-picker-large">
                        <?php foreach ($protocolOptions as $val => $label): ?>
                            <?php
                                $selected = array_values(array_filter($selectedProtocols, fn($p) => ($p['protocol'] ?? '') === $val));
                                $customValue = $selected[0]['custom_label'] ?? '';
                            ?>
                            <label class="protocol-option-card">
                                <input type="checkbox" name="protocols[]" value="<?= $val ?>" <?= $selected ? 'checked' : '' ?>>
                                <span class="protocol-option-mark"><?= icon('check', 15) ?></span>
                                <span class="protocol-option-copy"><strong><?= htmlspecialchars($label) ?></strong><small><?= $val === 'custom' ? 'نام دلخواه' : strtoupper($val) ?></small></span>
                            </label>
                            <?php if ($val === 'custom'): ?>
                                <div class="custom-protocol-wrap">
                                    <input class="custom-protocol-input" type="text" name="custom_protocol" maxlength="100" placeholder="مثلاً HTTP/2 یا WebDAV" value="<?= htmlspecialchars($customValue) ?>" <?= $selected ? '' : 'disabled' ?>>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="step-badge">۳</span>
                        <div><h2>دسته‌بندی و وضعیت</h2><p>دسته‌ها را انتخاب و وضعیت انتشار را کنترل کنید.</p></div>
                    </div>
                    <div class="editor-grid">
                        <div class="editor-panel">
                            <div class="editor-panel-title">دسته‌بندی‌ها</div>
                            <small class="form-hint" style="margin-top:-.4rem;margin-bottom:.7rem;">میرور می‌تواند در چند دسته باشد. والدها در tag نمایش داده می‌شوند.</small>
                            <div class="category-picker mirror-category-picker">
                                <?php if (empty($categories)): ?>
                                    <p class="empty-state" style="grid-column:1/-1;padding:1rem;">دسته‌بندی‌ای موجود نیست.</p>
                                <?php else: ?>
                                    <?php foreach (($categoryGroups[0] ?? []) as $root): ?>
                                        <div class="category-group-title"><?= htmlspecialchars($root['name']) ?></div>
                                        <?php foreach (($categoryGroups[(int)$root['id']] ?? []) as $child): ?>
                                            <label class="category-checkbox category-child">
                                                <input type="checkbox" name="category_ids[]" value="<?= $child['id'] ?>" <?= in_array((int)$child['id'], $currentCatIds, true) ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($child['name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    <?php foreach (($categoryGroups[0] ?? []) as $root): ?>
                                        <?php if (empty($categoryGroups[(int)$root['id']] ?? [])): ?>
                                            <label class="category-checkbox">
                                                <input type="checkbox" name="category_ids[]" value="<?= $root['id'] ?>" <?= in_array((int)$root['id'], $currentCatIds, true) ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($root['name']) ?></span>
                                            </label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="editor-panel">
                            <div class="editor-panel-title">وضعیت انتشار</div>
                            <label class="status-select-card">
                                <span class="status-select-copy"><strong>وضعیت میرور</strong><small>میرور غیرفعال در سایت عمومی نمایش داده نمی‌شود.</small></span>
                                <select id="status" name="status">
                                    <option value="active" <?= ($currentMirror['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
                                    <option value="inactive" <?= ($currentMirror['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="step-badge">۴</span>
                        <div><h2>توضیحات</h2><p>جزئیات تکمیلی برای کاربران سایت.</p></div>
                    </div>
                    <div class="form-group">
                        <label for="description">توضیحات</label>
                        <textarea id="description" name="description" rows="6" maxlength="1000" placeholder="توضیح درباره محتوا، توزیع‌ها، سرعت، محدوده دسترسی و..."><?= htmlspecialchars($currentMirror['description'] ?? $prefill['description'] ?? '') ?></textarea>
                        <div class="field-counter"><span id="description-count">۰</span> / ۱۰۰۰</div>
                    </div>
                </div>

                <div class="form-actions sticky-actions editor-actions">
                    <div class="form-submit-hint"><span class="status-dot"></span><span>ذخیره باعث بروزرسانی مستقیم اطلاعات میرور می‌شود.</span></div>
                    <div class="form-submit-buttons">
                        <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">انصراف</a>
                        <button type="submit" class="btn btn-primary btn-lg"><?= $action === 'edit' ? 'ذخیره تغییرات' : 'افزودن میرور' ?></button>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
                                    <input type="hidden" name="custom_protocols[]" value="">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="slug">نامک (Slug)</label>
                    <input type="text" id="slug" name="slug"
                        value="<?= htmlspecialchars($currentMirror['slug'] ?? '') ?>"
                        dir="ltr" placeholder="خودکار" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>دسته‌بندی‌ها <span class="required">*</span></label>
                    <small class="form-hint">می‌توانید میرور را به چند دسته‌بندی اختصاص دهید.</small>
                    <div class="category-picker">
                        <?php if (empty($categories)): ?>
                            <p class="empty-state" style="grid-column:1/-1;padding:1rem;">دسته‌بندی‌ای موجود نیست.</p>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <label class="category-checkbox">
                                    <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>"
                                        <?= in_array((int)$cat['id'], $currentCatIds, true) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">وضعیت</label>
                    <select id="status" name="status">
                        <option value="active" <?= ($currentMirror['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
                        <option value="inactive" <?= ($currentMirror['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">توضیحات</label>
                    <textarea id="description" name="description" rows="4"><?= htmlspecialchars($currentMirror['description'] ?? $prefill['description'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">انصراف</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>آدرس</th>
                        <th>پروتکل</th>
                        <th>دسته‌بندی‌ها</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mirrors)): ?>
                        <tr><td colspan="6" class="empty-state">میروری یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mirrors as $mirror): ?>
                            <?php $names = mirrorDisplayName($mirror); ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($names['fa']) ?>
                                    <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                                        <br><span class="ltr-text" style="font-size:0.75rem;"><?= htmlspecialchars($names['en']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="ltr-text"><?= htmlspecialchars(excerpt($mirror['url'], 40)) ?></td>
                                <td>
                                    <?= protocolBadges($mirror['protocols'] ?? getMirrorProtocols($pdo, $mirror['id'])) ?>
                                </td>
                                <td>
                                    <?php if (empty($mirror['categories'])): ?>
                                        <span class="badge badge-default">-</span>
                                    <?php else: ?>
                                        <?php foreach ($mirror['categories'] as $c): ?>
                                            <span class="badge"><?= htmlspecialchars($c['name']) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-<?= $mirror['status'] === 'active' ? 'success' : 'warning' ?>"><?= $mirror['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span></td>
                                <td class="actions">
                                    <a href="<?= htmlspecialchars($mirror['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline">مشاهده</a>
                                    <a href="?action=edit&id=<?= $mirror['id'] ?>" class="btn btn-sm btn-outline">ویرایش</a>
                                    <form action="?id=<?= $mirror['id'] ?>" method="POST" class="inline-form" data-confirm="آیا از حذف این میرور مطمئن هستید؟">
                                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
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
    <?php endif; ?>
</section>

<?php renderFooter(); ?>