<?php
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

if (!defined('DB_HOST'))
    define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME'))
    define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'levelpla_streaming');
if (!defined('DB_USER'))
    define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'levelpl-Administrador');
if (!defined('DB_PASS'))
    define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET'))
    define('DB_CHARSET', 'utf8mb4');

if (!defined('SMTP_HOST'))
    define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'mail.levelplaymax.com');
if (!defined('SMTP_USER'))
    define('SMTP_USER', $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: 'cuentastreaming@levelplaymax.com');
if (!defined('SMTP_PASS'))
    define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '');
if (!defined('SMTP_PORT'))
    define('SMTP_PORT', (int) ($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587));
if (!defined('SMTP_SECURE'))
    define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?: 'tls');
if (!defined('EMAIL_FROM_NAME'))
    define('EMAIL_FROM_NAME', $_ENV['EMAIL_FROM_NAME'] ?? getenv('EMAIL_FROM_NAME') ?: "LevelPlayMax");
if (!defined('EMAIL_ADMIN'))
    define('EMAIL_ADMIN', $_ENV['EMAIL_ADMIN'] ?? getenv('EMAIL_ADMIN') ?: '');

if (!defined('ONESIGNAL_APP_ID'))
    define('ONESIGNAL_APP_ID', $_ENV['ONESIGNAL_APP_ID'] ?? getenv('ONESIGNAL_APP_ID') ?: '');
if (!defined('ONESIGNAL_API_KEY'))
    define('ONESIGNAL_API_KEY', $_ENV['ONESIGNAL_API_KEY'] ?? getenv('ONESIGNAL_API_KEY') ?: '');

if (!defined('CSRF_TOKEN_NAME'))
    define('CSRF_TOKEN_NAME', 'csrf_token');
if (!defined('SESSION_LIFETIME'))
    define('SESSION_LIFETIME', 3600 * 8);
if (!defined('MAX_UPLOAD_SIZE'))
    define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
if (!defined('ALLOWED_UPLOAD_TYPES'))
    define('ALLOWED_UPLOAD_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']);

if (!defined('UPLOAD_DIR'))
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
if (!defined('LOG_DIR'))
    define('LOG_DIR', __DIR__ . '/../logs/');

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}