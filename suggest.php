<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

function normalizePhoneInput($value) {
    $value = strtr((string)$value, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
    return preg_replace('/[^0-9+]/', '', trim($value));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است. لطفاً دوباره تلاش کنید.');
        redirect(SITE_URL . '/suggest.php');
    }

    $name_fa = sanitize($_POST['name_fa'] ?? '');
    $name_en = sanitize($_POST['name_en'] ?? '');
    $protocols = normalizeProtocols($_POST['protocols'] ?? [], $_POST['custom_protocols'] ?? []);
    $description = sanitize($_POST['description'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = normalizePhoneInput($_POST['phone'] ?? '');

    $links = [];
    foreach ((array)($_POST['urls'] ?? []) as $rawUrl) {
        $url = filter_var(trim((string)$rawUrl), FILTER_SANITIZE_URL);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) continue;
        if (mb_strlen($url) > 1000) continue;
        if (!in_array($url, $links, true)) $links[] = $url;
    }

    if (empty($name_fa) || empty($links) || empty($protocols) || empty($phone)) {
        flash('error', 'نام، حداقل یک لینک، حداقل یک پروتکل و شماره همراه الزامی است.');
        redirect(SITE_URL . '/suggest.php');
    }

    if (mb_strlen($phone) < 10 || mb_strlen($phone) > 15 || !preg_match('/^\+?[0-9]+$/', $phone)) {
        flash('error', 'شماره همراه واردشده معتبر نیست.');
        redirect(SITE_URL . '/suggest.php');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'آدرس ایمیل واردشده معتبر نیست.');
        redirect(SITE_URL . '/suggest.php');
    }

    try {
        $protocol = $protocols[0]['protocol'];
        $stmt = $pdo->prepare(
            "INSERT INTO requests (name_fa, name_en, url, protocol, parent_category_id, category_id, category_name, description, status, email, phone)
             VALUES (?, ?, ?, ?, NULL, NULL, NULL, ?, 'pending', ?, ?)"
        );
        $stmt->execute([
            $name_fa,
            $name_en,
            $links[0],
            $protocol,
            $description,
            $email !== '' ? $email : null,
            $phone
        ]);

        $requestId = (int)$pdo->lastInsertId();
        setRequestProtocols($pdo, $requestId, $protocols);
        setRequestLinks($pdo, $requestId, $links);

        flash('success', 'پیشنهاد شما با موفقیت ثبت شد و پس از بررسی مدیران منتشر خواهد شد.');
        redirect(SITE_URL . '/suggest.php');
    } catch (PDOException $e) {
        flash('error', 'خطا در ثبت پیشنهاد. لطفاً دوباره تلاش کنید.');
        redirect(SITE_URL . '/suggest.php');
    }
}

$protocolOptions = getProtocolOptions();
renderHeader('پیشنهاد میرور جدید');
?>

<section class="section suggestion-page">
    <div class="page-hero">
        <div class="page-hero-icon"><?= icon('plus-circle', 24) ?></div>
        <div>
            <span class="eyebrow">MirrorHub • پیشنهاد جامعه</span>
            <h1 class="section-title">پیشنهاد یا درخواست میرور جدید</h1>
            <p class="section-desc">اطلاعات میرور و راه‌های دسترسی را وارد کنید تا بعد از بررسی مدیر، به فهرست عمومی اضافه شود.</p>
        </div>
    </div>

    <form action="<?= SITE_URL ?>/suggest.php" method="POST" class="smart-form card" id="suggestion-form">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۱</span>
                <div><h2>اطلاعات میرور</h2><p>نام میرور را ثبت کنید.</p></div>
            </div>
            <div class="form-row">
                <div class="form-group flex-1">
                    <label for="name_fa">نام فارسی میرور <span class="required">*</span></label>
                    <input type="text" id="name_fa" name="name_fa" required maxlength="120" placeholder="مثلاً: آرشیو اوبونتو" autocomplete="off">
                </div>
                <div class="form-group flex-1">
                    <label for="name_en">نام انگلیسی میرور <span class="form-optional">اختیاری</span></label>
                    <input type="text" id="name_en" name="name_en" maxlength="120" placeholder="Ubuntu Archive" dir="ltr" autocomplete="off">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۲</span>
                <div><h2>لینک‌های میرور</h2><p>هر تعداد لینک که در اختیار دارید اضافه کنید.</p></div>
            </div>
            <div class="request-links-list" id="request-links-list">
                <div class="request-link-row">
                    <span class="request-link-index">۱</span>
                    <input type="url" name="urls[]" required maxlength="1000" placeholder="https://example.com/mirror" dir="ltr" autocomplete="off">
                    <button type="button" class="request-link-remove btn btn-sm btn-outline" aria-label="حذف لینک" disabled><?= icon('x', 15) ?></button>
                </div>
            </div>
            <button type="button" class="btn btn-outline request-link-add" id="add-request-link">
                <?= icon('plus-circle', 15) ?><span>افزودن لینک</span>
            </button>
            <div class="selection-note request-link-note">
                <span class="selection-dot"></span>
                می‌توانید هر تعداد مسیر دسترسی را ثبت کنید؛ همه لینک‌ها برای بررسی مدیر ذخیره می‌شوند.
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۳</span>
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
                <span class="step-badge">۴</span>
                <div><h2>اطلاعات هماهنگی</h2><p>برای پیگیری و هماهنگی، شماره همراه الزامی است.</p></div>
            </div>
            <div class="form-row">
                <div class="form-group flex-1">
                    <label for="phone">شماره همراه <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required maxlength="20" inputmode="tel" placeholder="مثلاً 09121234567" dir="ltr" autocomplete="tel">
                </div>
                <div class="form-group flex-1">
                    <label for="email">ایمیل <span class="form-optional">اختیاری</span></label>
                    <input type="email" id="email" name="email" maxlength="190" placeholder="you@example.com" dir="ltr" autocomplete="email">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="step-badge">۵</span>
                <div><h2>توضیحات</h2><p>جزئیات مفید درباره محتوا، سرعت یا کاربرد میرور را بنویسید.</p></div>
            </div>
            <div class="form-group">
                <label for="description">توضیحات <span class="form-optional">اختیاری</span></label>
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