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
    $protocols = normalizeProtocols($_POST['protocols'] ?? [], $_POST['custom_protocols'] ?? []);
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
        <?php
            $categoryGroups = [];
            foreach ($categories as $cat) {
                $parentId = (int)($cat['parent_id'] ?? 0);
                $categoryGroups[$parentId][] = $cat;
            }
        ?>

        <div class="mirror-form-page">
            <div class="mirror-form-header">
                <div class="mirror-form-title-wrap">
                    <div class="mirror-form-icon"><?= icon('server', 22) ?></div>
                    <div>
                        <span class="eyebrow">MirrorHub • مدیریت میرور</span>
                        <h2><?= $action === 'edit' ? 'ویرایش میرور' : 'افزودن میرور جدید' ?></h2>
                        <p>اطلاعات میرور را مرحله‌به‌مرحله وارد کنید و قبل از ذخیره یک نگاه سریع به نتیجه داشته باشید.</p>
                    </div>
                </div>
                <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">بازگشت</a>
            </div>

            <?php if ($prefillRequestId): ?>
                <div class="mirror-request-note">
                    <span class="mirror-request-note-icon"><?= icon('inbox', 16) ?></span>
                    <div>
                        <strong>درخواست #<?= $prefillRequestId ?> متصل است</strong>
                        <span>اطلاعات درخواست در این فرم پیش‌فرض شده و بعد از ذخیره به همان درخواست متصل می‌ماند.</span>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= SITE_URL ?>/admin/mirrors.php?action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>" method="POST" class="mirror-form-shell" data-require-category>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                <?php if ($prefillRequestId): ?><input type="hidden" name="link_request_id" value="<?= $prefillRequestId ?>"><?php endif; ?>

                <div class="mirror-form-layout">
                    <div class="mirror-form-main">
                        <section class="mirror-form-card">
                            <div class="mirror-form-card-head">
                                <div>
                                    <span class="mirror-step">۱</span>
                                    <div class="mirror-step-copy">
                                        <h3>مشخصات اصلی</h3>
                                        <p>نام و آدرس قابل استفاده کاربران.</p>
                                    </div>
                                </div>
                                <span class="mirror-form-required"><span class="required">*</span> فیلدهای الزامی</span>
                            </div>

                            <div class="mirror-form-grid two">
                                <div class="form-group">
                                    <label for="name_fa">نام فارسی <span class="required">*</span></label>
                                    <input type="text" id="name_fa" name="name_fa" value="<?= htmlspecialchars($currentMirror['name_fa'] ?? $prefill['name_fa'] ?? '') ?>" required autocomplete="off" placeholder="مثلاً میرور اوبونتو">
                                </div>
                                <div class="form-group">
                                    <label for="name_en">نام انگلیسی</label>
                                    <input type="text" id="name_en" name="name_en" value="<?= htmlspecialchars($currentMirror['name_en'] ?? $prefill['name_en'] ?? '') ?>" dir="ltr" autocomplete="off" placeholder="Ubuntu Mirror">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="url">آدرس میرور <span class="required">*</span></label>
                                <div class="mirror-url-field">
                                    <span class="mirror-url-prefix"><?= icon('eye', 14) ?> URL</span>
                                    <input type="url" id="url" name="url" value="<?= htmlspecialchars($currentMirror['url'] ?? $prefill['url'] ?? '') ?>" dir="ltr" required placeholder="https://example.com/mirror" autocomplete="url">
                                </div>
                                <small class="form-hint">آدرس کامل و قابل دسترس میرور را وارد کنید.</small>
                            </div>

                            <div class="mirror-form-grid two">
                                <div class="form-group">
                                    <label for="slug">نامک (Slug)</label>
                                    <div class="mirror-slug-field">
                                        <span>/category/</span>
                                        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($currentMirror['slug'] ?? '') ?>" dir="ltr" placeholder="خودکار" autocomplete="off">
                                    </div>
                                    <small class="form-hint">برای آدرس یکتا استفاده می‌شود.</small>
                                </div>
                                <div class="form-group">
                                    <label for="status">وضعیت انتشار</label>
                                    <div class="mirror-status-wrap">
                                        <select id="status" name="status">
                                            <option value="active" <?= ($currentMirror['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال و قابل نمایش</option>
                                            <option value="inactive" <?= ($currentMirror['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غیرفعال و مخفی</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="mirror-form-card">
                            <div class="mirror-form-card-head">
                                <div>
                                    <span class="mirror-step">۲</span>
                                    <div class="mirror-step-copy">
                                        <h3>پروتکل‌های دسترسی</h3>
                                        <p>هر تعداد روش دسترسی که میرور پشتیبانی می‌کند انتخاب کنید.</p>
                                    </div>
                                </div>
                                <span class="mirror-count-badge" id="protocol-selected-count">۰ انتخاب</span>
                            </div>

                            <div class="mirror-protocol-grid">
                                <?php foreach ($protocolOptions as $value => $label): ?>
                                    <?php
                                        $selected = array_values(array_filter($selectedProtocols, fn($item) => ($item['protocol'] ?? '') === $value));
                                        $customValue = $selected[0]['custom_label'] ?? '';
                                    ?>
                                    <label class="mirror-protocol-card">
                                        <input type="checkbox" name="protocols[]" value="<?= htmlspecialchars($value) ?>" <?= $selected ? 'checked' : '' ?>>
                                        <span class="mirror-protocol-check"><?= icon('check', 14) ?></span>
                                        <span class="mirror-protocol-main">
                                            <strong><?= htmlspecialchars($label) ?></strong>
                                            <small><?= $value === 'custom' ? 'پروتکل دلخواه' : strtoupper($value) ?></small>
                                        </span>
                                        <span class="mirror-protocol-code"><?= htmlspecialchars($value) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <?php $customSelected = !empty(array_filter($selectedProtocols, fn($item) => ($item['protocol'] ?? '') === 'custom')); ?>
                            <div class="mirror-custom-protocol <?= $customSelected ? 'is-visible' : '' ?>" id="custom-protocol-panel">
                                <div class="mirror-custom-icon"><?= icon('activity', 17) ?></div>
                                <div>
                                    <strong>پروتکل سفارشی</strong>
                                    <span>نام دقیق پروتکل را وارد کنید.</span>
                                </div>
                                <input class="custom-protocol-input" type="text" name="custom_protocol" maxlength="100" placeholder="مثلاً WebDAV" value="<?= htmlspecialchars($customValue ?? '') ?>" <?= $customSelected ? '' : 'disabled' ?>>
                            </div>
                        </section>

                        <section class="mirror-form-card">
                            <div class="mirror-form-card-head">
                                <div>
                                    <span class="mirror-step">۳</span>
                                    <div class="mirror-step-copy">
                                        <h3>دسته‌بندی</h3>
                                        <p>میرور را به یک یا چند زیر‌دسته مرتبط کنید.</p>
                                    </div>
                                </div>
                                <span class="mirror-count-badge" id="category-selected-count">۰ انتخاب</span>
                            </div>

                            <div class="mirror-category-box">
                                <?php foreach (($categoryGroups[0] ?? []) as $root): ?>
                                    <?php $children = $categoryGroups[(int)$root['id']] ?? []; ?>
                                    <div class="mirror-category-group">
                                        <div class="mirror-category-parent">
                                            <span><?= htmlspecialchars($root['name']) ?></span>
                                            <small><?= count($children) ? count($children) . ' زیر‌دسته' : 'بدون زیر‌دسته' ?></small>
                                        </div>

                                        <?php if ($children): ?>
                                            <div class="mirror-category-items">
                                                <?php foreach ($children as $child): ?>
                                                    <label class="mirror-category-item">
                                                        <input type="checkbox" name="category_ids[]" value="<?= (int)$child['id'] ?>" <?= in_array((int)$child['id'], $currentCatIds, true) ? 'checked' : '' ?>>
                                                        <span class="mirror-category-item-check"><?= icon('check', 13) ?></span>
                                                        <span><?= htmlspecialchars($child['name']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <label class="mirror-category-item standalone">
                                                <input type="checkbox" name="category_ids[]" value="<?= (int)$root['id'] ?>" <?= in_array((int)$root['id'], $currentCatIds, true) ? 'checked' : '' ?>>
                                                <span class="mirror-category-item-check"><?= icon('check', 13) ?></span>
                                                <span>استفاده از «<?= htmlspecialchars($root['name']) ?>»</span>
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (empty($categories)): ?>
                                    <div class="mirror-empty-inline">
                                        <?= icon('folder', 18) ?>
                                        <span>هنوز دسته‌بندی‌ای ایجاد نشده است.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="mirror-form-card">
                            <div class="mirror-form-card-head">
                                <div>
                                    <span class="mirror-step">۴</span>
                                    <div class="mirror-step-copy">
                                        <h3>توضیحات</h3>
                                        <p>اطلاعات تکمیلی برای نمایش به کاربران.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mirror-description-group">
                                <textarea id="description" name="description" rows="7" maxlength="1000" placeholder="مثلاً این میرور بسته‌های Ubuntu، Debian و... را با سرعت بالا ارائه می‌کند."><?= htmlspecialchars($currentMirror['description'] ?? $prefill['description'] ?? '') ?></textarea>
                                <div class="mirror-description-footer">
                                    <span>می‌توانید درباره محتوا، سرعت یا محدوده سرویس توضیح دهید.</span>
                                    <strong><span id="description-count">۰</span> / ۱۰۰۰</strong>
                                </div>
                            </div>
                        </section>
                    </div>

                    <aside class="mirror-form-side">
                        <div class="mirror-side-card mirror-live-card">
                            <div class="mirror-side-head">
                                <div><span>پیش‌نمایش</span><strong>نمایش کارت</strong></div>
                                <span class="mirror-live-dot"><i></i> زنده</span>
                            </div>

                            <div class="mirror-preview">
                                <div class="mirror-preview-top">
                                    <span class="mirror-preview-icon"><?= icon('server', 17) ?></span>
                                    <span class="badge badge-success" id="preview-status">فعال</span>
                                </div>
                                <strong id="preview-name">نام میرور</strong>
                                <small id="preview-name-en">Mirror Name</small>
                                <p id="preview-description">توضیحات میرور در اینجا نمایش داده می‌شود.</p>
                                <div class="mirror-preview-tags" id="preview-protocols"><span class="badge badge-primary">HTTPS</span></div>
                            </div>

                            <div class="mirror-preview-url" id="preview-url">https://example.com/mirror</div>
                        </div>

                        <div class="mirror-side-card">
                            <div class="mirror-side-head"><div><span>وضعیت فرم</span><strong>آمادگی ذخیره</strong></div></div>
                            <div class="mirror-form-checklist">
                                <div id="check-name"><span></span><p>نام میرور</p></div>
                                <div id="check-url"><span></span><p>آدرس معتبر</p></div>
                                <div id="check-protocol"><span></span><p>حداقل یک پروتکل</p></div>
                                <div id="check-category"><span></span><p>حداقل یک دسته</p></div>
                            </div>
                        </div>

                        <div class="mirror-side-tip">
                            <span><?= icon('info', 16) ?></span>
                            <p>پروتکل‌ها و دسته‌بندی‌ها می‌توانند چندتایی باشند؛ انتخاب‌ها در کارت میرور به‌صورت tag نمایش داده می‌شوند.</p>
                        </div>
                    </aside>
                </div>

                <div class="mirror-savebar">
                    <div class="mirror-savebar-copy">
                        <span class="status-dot"></span>
                        <div><strong><?= $action === 'edit' ? 'آماده ذخیره تغییرات' : 'آماده ثبت میرور جدید' ?></strong><small>اطلاعات را بررسی کنید و سپس ذخیره کنید.</small></div>
                    </div>
                    <div class="mirror-savebar-actions">
                        <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">انصراف</a>
                        <button type="submit" class="btn btn-primary btn-lg"><?= $action === 'edit' ? 'ذخیره تغییرات' : 'افزودن میرور' ?></button>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
            <a href="<?= SITE_URL ?>/admin/mirrors.php?action=add" class="btn btn-primary">افزودن میرور جدید</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="form-container card">
            <h2><?= $action === 'edit' ? 'ویرایش میرور' : 'افزودن میرور جدید' ?></h2>
            <?php if ($prefillRequestId): ?>
                <div class="alert alert-success" style="margin-bottom:1rem;">
                    این میرور بر اساس درخواست #<?= $prefillRequestId ?> در حال ساخت است. پس از ذخیره، به‌صورت خودکار به آن متصل می‌شود.
                </div>
            <?php endif; ?>
            <form action="<?= SITE_URL ?>/admin/mirrors.php?action=<?= $action ?>&id=<?= $id ?>" method="POST" data-require-category>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                <?php if ($prefillRequestId): ?>
                    <input type="hidden" name="link_request_id" value="<?= $prefillRequestId ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="name_fa">نام فارسی <span class="required">*</span></label>
                        <input type="text" id="name_fa" name="name_fa"
                            value="<?= htmlspecialchars($currentMirror['name_fa'] ?? $prefill['name_fa'] ?? '') ?>"
                            required autocomplete="off">
                    </div>
                    <div class="form-group flex-1">
                        <label for="name_en">نام انگلیسی</label>
                        <input type="text" id="name_en" name="name_en"
                            value="<?= htmlspecialchars($currentMirror['name_en'] ?? $prefill['name_en'] ?? '') ?>"
                            dir="ltr" autocomplete="off">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group flex-2">
                        <label for="url">آدرس اینترنتی (URL) <span class="required">*</span></label>
                        <input type="url" id="url" name="url"
                            value="<?= htmlspecialchars($currentMirror['url'] ?? $prefill['url'] ?? '') ?>"
                            dir="ltr" required placeholder="https://example.com/mirror" autocomplete="off">
                    </div>
                    <div class="form-group flex-1">
                        <label>پروتکل‌ها <span class="required">*</span></label>
                        <small class="form-hint">چند پروتکل را انتخاب کنید؛ برای «سفارشی» نام پروتکل را وارد کنید.</small>
                        <div class="protocol-picker">
                            <?php
                            $selectedProtocols = $currentProtocols;
                            if (empty($selectedProtocols) && !empty($prefill['protocol'])) {
                                $selectedProtocols = normalizeProtocols([$prefill['protocol']]);
                            }
                            foreach ($protocolOptions as $val => $label):
                                $selected = array_values(array_filter($selectedProtocols, fn($p) => $p['protocol'] === $val));
                                $customValue = $selected[0]['custom_label'] ?? '';
                            ?>
                                <label class="protocol-checkbox">
                                    <input type="checkbox" name="protocols[]" value="<?= $val ?>" <?= $selected ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                                <?php if ($val === 'custom'): ?>
                                    <input class="custom-protocol-input" type="text" name="custom_protocols[]" maxlength="100" placeholder="مثلاً: HTTP/2" value="<?= htmlspecialchars($customValue) ?>" <?= $selected ? '' : 'disabled' ?>>
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