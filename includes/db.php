<?php
require_once __DIR__ . '/../config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== 'install.php') {
        http_response_code(500);
        if (ENVIRONMENT === 'development') {
            die("خطا در اتصال به پایگاه داده: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        die("خطا در اتصال به پایگاه داده. لطفاً ابتدا فایل install.php را اجرا کنید.");
    }
}