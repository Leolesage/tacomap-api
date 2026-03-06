<?php
declare(strict_types=1);

use App\Core\Env;

$parseCsv = static function (string $value): array {
    $parts = array_map('trim', explode(',', $value));
    return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
};

return [
    'app' => [
        'url' => Env::get('APP_URL', 'http://localhost:8001'),
        'admin_detail_base_url' => Env::get('ADMIN_DETAIL_BASE_URL', ''),
        'admin_notify_email' => Env::get('ADMIN_NOTIFY_EMAIL', ''),
    ],
    'db' => [
        'dsn' => Env::get('DB_DSN', ''),
        'user' => Env::get('DB_USER', ''),
        'pass' => Env::get('DB_PASS', ''),
    ],
    'jwt' => [
        'secret' => Env::get('JWT_SECRET', 'change-me'),
        'exp' => (int)Env::get('JWT_EXP_SECONDS', '3600'),
    ],
    'cors' => [
        'allowed_origins' => $parseCsv(Env::get('CORS_ALLOWED_ORIGINS', 'http://localhost:8000')),
        'allowed_methods' => Env::get('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'),
        'allowed_headers' => Env::get('CORS_ALLOWED_HEADERS', 'Authorization,Content-Type'),
        'max_age' => (int)Env::get('CORS_MAX_AGE', '86400'),
    ],
    'uploads' => [
        'max_size_bytes' => (int)Env::get('UPLOAD_MAX_SIZE_BYTES', '3145728'),
        'allowed_extensions' => $parseCsv(Env::get('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,webp')),
        'allowed_mimes' => $parseCsv(Env::get('UPLOAD_ALLOWED_MIMES', 'image/jpeg,image/png,image/webp')),
    ],
    'smtp' => [
        'host' => Env::get('SMTP_HOST', ''),
        'port' => (int)Env::get('SMTP_PORT', '587'),
        'user' => Env::get('SMTP_USER', ''),
        'pass' => Env::get('SMTP_PASS', ''),
        'secure' => Env::get('SMTP_SECURE', 'tls'),
        'timeout' => (int)Env::get('SMTP_TIMEOUT', '10'),
        'from_email' => Env::get('SMTP_FROM_EMAIL', 'no-reply@tacomap.local'),
        'from_name' => Env::get('SMTP_FROM_NAME', 'TacoMap France'),
    ],
];
