<?php
declare(strict_types=1);

namespace App\Core;

final class Cors
{
    public static function handle(array $config): void
    {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $allowedOrigins = $config['allowed_origins'] ?? [];
        $allowedMethods = (string)($config['allowed_methods'] ?? 'GET,POST,PUT,DELETE,OPTIONS');
        $allowedHeaders = (string)($config['allowed_headers'] ?? 'Authorization,Content-Type');
        $maxAge = (int)($config['max_age'] ?? 86400);

        if ($origin !== '') {
            if (!in_array($origin, $allowedOrigins, true)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'cors_not_allowed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }

            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Allow-Headers: ' . $allowedHeaders);
        header('Access-Control-Max-Age: ' . $maxAge);

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
