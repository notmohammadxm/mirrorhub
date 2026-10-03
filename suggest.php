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
    $protocol = in_array($_POST['protocol'] ?? '', array_keys(getProtocolOptions()), true) ? $_POST['protocol'] : 'https';
    $category_name = sanitize($_POST['category_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if (empty($name_fa) || empty($url) || empty($category_name)) {
        flash('error', 'لطفاً تمام فیلدهای ضروری را پر کنید.');
        redirect(SITE_URL . '/suggest.php');
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'لطفاً یک آدرس اینترنتی معتبر وارد کنید.');
        redirect(SITE_URL . '/suggest.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO requests (name_fa, name_en, url, protocol, category_name, description, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$name_fa, $name_en, $url, $protocol, $category_name, $description]);
        flash('success', 'پیشنهاد شما با موفقیت ثبت شد و پس از بررسی مدیران منتشر خواهد شد.');
        redirect(SITE_URL . '/suggest.php');
    } catch (PDOException $e) {
        flash('error', 'خطا در ثبت پیشنهاد. لطفاً دوباره تلاش کنید.');
        redirect(SITE_URL . '/suggest.php');
    }
}

$categories = getCategories($pdo, null);
$protocolOptions = getProtocolOptions();
renderHeader('پیشنهاد میرور جدید');
?>

<section class="section">
    <h1 class="section-title">پیشنهاد یا درخواست میرور جدید</h1>
    <p class="section-desc">اگر میروری می‌شناسید که در فهرست ما وجود ندارد، فرم زیر را تکمیل کنید. درخواست شما پس از بررسی توسط مدیران به فهرست اضافه خواهد شد.</p>

    <form action="<?= SITE_URL ?>/suggest.php" method="POST" class="form-container card">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
        
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

        <div class="form-row">
            <div class="form-group flex-2">
                <label for="url">آدرس اینترنتی (URL) <span class="required">*</span></label>
                <input type="url" id="url" name="url" required maxlength="255" placeholder="https://example.com/mirror" dir="ltr" autocomplete="off">
            </div>
            <div class="form-group flex-1">
                <label for="protocol">پروتکل <span class="required">*</span></label>
                <select id="protocol" name="protocol" required>
                    <?php foreach ($protocolOptions as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $val === 'https' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="category_name">نام دسته‌بندی <span class="required">*</span></label>
            <input type="text" id="category_name" name="category_name" required maxlength="120" placeholder="مثلاً: توزیع‌های لینوکس" autocomplete="off">
            <?php if (!empty($categories)): ?>
                <small class="form-hint">می‌توانید یکی از دسته‌بندی‌های موجود را انتخاب کنید یا نام دسته جدیدی را وارد نمایید:</small>
                <div class="category-suggestions">
                    <?php foreach ($categories as $cat): ?>
                        <span class="suggestion-chip" data-value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="description">توضیحات (اختیاری)</label>
            <textarea id="description" name="description" rows="4" maxlength="1000" placeholder="توضیحاتی درباره میرور، کاربرد آن یا دلیل نیاز به آن..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">ثبت پیشنهاد</button>
            <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline">انصراف</a>
        </div>
    </form>
</section>

<?php renderFooter(); ?>