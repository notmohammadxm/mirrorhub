<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است. لطفاً دوباره تلاش کنید.');
        redirect(SITE_URL . '/suggest.php');
    }

    $name_fa = sanitize($_POST['name_fa'] ?? '');
    $name_en = sanitize($_POST['name_en'] ?? '');
    $url = filter_var(trim($_POST['url'] ?? ''), FILTER_SANITIZE_URL);
    $protocols = normalizeProtocols($_POST['protocols'] ?? [], $_POST['custom_protocols'] ?? []);
    $parent_category_id = (int)($_POST['parent_category_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $parentCategory = $parent_category_id ? getCategoryById($pdo, $parent_category_id) : null;
    $childCategory = $category_id ? getCategoryById($pdo, $category_id) : null;
    $category_name = $parentCategory && $childCategory && (int)$childCategory['parent_id'] === $parent_category_id
        ? $parentCategory['name'] . ' / ' . $childCategory['name']
        : '';
    $description = sanitize($_POST['description'] ?? '');

    if (empty($name_fa) || empty($url) || empty($protocols) || !$parentCategory || !$childCategory || (int)$childCategory['parent_id'] !== $parent_category_id) {
        flash('error', 'لطفاً تمام فیلدهای ضروری را پر کنید.');
        redirect(SITE_URL . '/suggest.php');
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'لطفاً یک آدرس اینترنتی معتبر وارد کنید.');
        redirect(SITE_URL . '/suggest.php');
    }

    try {
        $protocol = $protocols[0]['protocol'];
        $stmt = $pdo->prepare("INSERT INTO requests (name_fa, name_en, url, protocol, parent_category_id, category_id, category_name, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$name_fa, $name_en, $url, $protocol, $parent_category_id, $category_id, $category_name, $description]);
        setRequestProtocols($pdo, (int)$pdo->lastInsertId(), $protocols);
        flash('success', 'پیشنهاد شما با موفقیت ثبت شد و پس از بررسی مدیران منتشر خواهد شد.');
        redirect(SITE_URL . '/suggest.php');
    } catch (PDOException $e) {
        flash('error', 'خطا در ثبت پیشنهاد. لطفاً دوباره تلاش کنید.');
        redirect(SITE_URL . '/suggest.php');
    }
}

$parentCategories = getCategories($pdo, null);
$allCategories = getAllCategories($pdo);
$childCategories = array_values(array_filter($allCategories, fn($cat) => !empty($cat['parent_id'])));
$protocolOptions = getProtocolOptions();
renderHeader('پیشنهاد میرور جدید');
?>

<section class="section suggestion-page">
    <div class="page-hero">
        <div class="page-hero-icon"><?= icon('plus-circle', 24) ?></div>
        <div>
            <span class="eyebrow">MirrorHub • پیشنهاد جامعه</span>
            <h1 class="section-title">پیشنهاد یا درخواست میرور جدید</h1>
            <p class="section-desc">اطلاعات میرور را وارد کنید تا بعد از بررسی مدیر، به فهرست عمومی اضافه شود.</p>
        </div>
    </div>

    <form action="<?= SITE_URL ?>/suggest.php" method="POST" class="smart-form card" id="suggestion-form">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۱</span>
                <div><h2>اطلاعات میرور</h2><p>نام و آدرس سرویس را ثبت کنید.</p></div>
            </div>
            <div class="form-row">
                <div class="form-group flex-1">
                    <label for="name_fa">نام فارسی میرور <span class="required">*</span></label>
                    <input type="text" id="name_fa" name="name_fa" required maxlength="120" placeholder="مثلاً: آرشیو اوبونتو" autocomplete="off">
                </div>
                <div class="form-group flex-1">
                    <label for="name_en">نام انگلیسی میرور</label>
                    <input type="text" id="name_en" name="name_en" maxlength="120" placeholder="Ubuntu Archive" dir="ltr" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label for="url">آدرس اینترنتی (URL) <span class="required">*</span></label>
                <div class="input-with-prefix"><span>URL</span><input type="url" id="url" name="url" required maxlength="255" placeholder="https://example.com/mirror" dir="ltr" autocomplete="off"></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۲</span>
                <div><h2>پروتکل‌های دسترسی</h2><p>هر تعداد پروتکل که میرور پشتیبانی می‌کند انتخاب کنید.</p></div>
            </div>
            <div class="protocol-picker protocol-picker-large">
                <?php foreach ($protocolOptions as $val => $label): ?>
                    <label class="protocol-option-card">
                        <input type="checkbox" name="protocols[]" value="<?= $val ?>" <?= $val === 'https' ? 'checked' : '' ?>>
                        <span class="protocol-option-mark"><?= icon('check', 15) ?></span>
                        <span class="protocol-option-copy"><strong><?= htmlspecialchars($label) ?></strong><small><?= $val === 'custom' ? 'پروتکل دلخواه' : strtoupper($val) ?></small></span>
                    </label>
                    <?php if ($val === 'custom'): ?>
                        <div class="custom-protocol-wrap">
                            <input class="custom-protocol-input" type="text" name="custom_protocols[]" maxlength="100" placeholder="مثلاً: HTTP/2" disabled>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="custom_protocols[]" value="">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۳</span>
                <div><h2>دسته‌بندی</h2><p>برای مرتب‌سازی دقیق، انتخاب والد و زیر‌دسته هر دو اجباری است.</p></div>
            </div>
            <div class="form-row">
                <div class="form-group flex-1">
                    <label for="parent_category_id">والد <span class="required">*</span></label>
                    <select id="parent_category_id" name="parent_category_id" required>
                        <option value="">انتخاب دسته والد...</option>
                        <?php foreach ($parentCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group flex-1">
                    <label for="category_id">زیر‌دسته <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required disabled>
                        <option value="">ابتدا والد را انتخاب کنید...</option>
                        <?php foreach ($childCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" data-parent="<?= $cat['parent_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="selection-note"><span class="selection-dot"></span> والد فقط برای ساختار و نمایش متادیتا استفاده می‌شود؛ لینک اصلی به زیر‌دسته خواهد بود.</div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۴</span>
                <div><h2>توضیحات</h2><p>جزئیات مفید درباره محتوا، سرعت یا کاربرد میرور را بنویسید.</p></div>
            </div>
            <div class="form-group">
                <label for="description">توضیحات (اختیاری)</label>
                <textarea id="description" name="description" rows="5" maxlength="1000" placeholder="مثلاً: میرور بسته‌های Ubuntu و Debian با دسترسی سریع داخلی..."></textarea>
                <div class="field-counter"><span id="description-count">۰</span> / ۱۰۰۰</div>
            </div>
        </div>

        <div class="form-actions sticky-actions">
            <div class="form-submit-hint"><span class="status-dot"></span><span>قبل از ثبت، اطلاعات را بررسی کنید.</span></div>
            <div class="form-submit-buttons">
                <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline">انصراف</a>
                <button type="submit" class="btn btn-primary btn-lg">ثبت پیشنهاد میرور</button>
            </div>
        </div>
    </form>
</section>

<?php renderFooter(); ?>