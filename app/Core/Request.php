<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public string $method = 'GET';
    public string $path = '/';
    public array $query = [];
    public array $headers = [];
    public array $body = [];
    public array $files = [];
    public array $params = [];
    public ?array $user = null;
    public string $contentType = '';
    public string $rawBody = '';

    public static function capture(): self
    {
        $request = new self();
        $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        $request->path = $path === '' ? '/' : $path;
        $request->query = $_GET ?? [];

        $request->headers = self::collectHeaders();
        $request->contentType = strtolower($request->headers['content-type'] ?? '');

        $request->rawBody = file_get_contents('php://input') ?: '';

        if ($request->method === 'POST') {
            if (str_contains($request->contentType, 'application/json')) {
                $request->body = self::decodeJson($request->rawBody);
            } elseif (str_contains($request->contentType, 'multipart/form-data')) {
                $request->body = $_POST ?? [];
                $request->files = $_FILES ?? [];
            } elseif (str_contains($request->contentType, 'application/x-www-form-urlencoded')) {
                $request->body = $_POST ?? [];
            }
        }

        if (in_array($request->method, ['PUT', 'PATCH'], true)) {
            if (str_contains($request->contentType, 'application/json')) {
                $request->body = self::decodeJson($request->rawBody);
            } elseif (str_contains($request->contentType, 'multipart/form-data')) {
                [$fields, $files] = self::parseMultipartBody($request->rawBody, $request->contentType);
                $request->body = $fields;
                $request->files = $files;
            } elseif (str_contains($request->contentType, 'application/x-www-form-urlencoded')) {
                $fields = [];
                parse_str($request->rawBody, $fields);
                $request->body = $fields;
            }
        }

        return $request;
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function isMultipart(): bool
    {
        return str_contains($this->contentType, 'multipart/form-data');
    }

    public function json(): array
    {
        return $this->body;
    }

    private static function collectHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$name] = $value;
                }
            }
        }
        return $headers;
    }

    private static function decodeJson(string $raw): array
    {
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function parseMultipartBody(string $rawBody, string $contentType): array
    {
        $fields = [];
        $files = [];

        if (!preg_match('/boundary=([^;]+)/', $contentType, $matches)) {
            return [$fields, $files];
        }

        $boundary = trim($matches[1], '"');
        $delimiter = '--' . $boundary;
        $parts = explode($delimiter, $rawBody);

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            $part = rtrim($part, "\r\n");

            if ($part === '' || $part === '--') {
                continue;
            }

            [$rawHeaders, $content] = array_pad(explode("\r\n\r\n", $part, 2), 2, '');
            $content = rtrim($content, "\r\n");

            $headers = [];
            foreach (explode("\r\n", $rawHeaders) as $headerLine) {
                if (strpos($headerLine, ':') === false) {
                    continue;
                }
                [$hKey, $hValue] = explode(':', $headerLine, 2);
                $headers[strtolower(trim($hKey))] = trim($hValue);
            }

            $disposition = $headers['content-disposition'] ?? '';
            if (!preg_match('/name="([^"]+)"/', $disposition, $nameMatch)) {
                continue;
            }
            $fieldName = $nameMatch[1];

            if (preg_match('/filename="([^"]*)"/', $disposition, $fileMatch)) {
                $fileName = $fileMatch[1];
                if ($fileName === '') {
                    continue;
                }
                $tmpPath = tempnam(sys_get_temp_dir(), 'up_');
                if ($tmpPath === false) {
                    continue;
                }
                file_put_contents($tmpPath, $content);

                $files[$fieldName] = [
                    'name' => $fileName,
                    'type' => $headers['content-type'] ?? 'application/octet-stream',
                    'tmp_name' => $tmpPath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => strlen($content),
                ];
            } else {
                $fields[$fieldName] = $content;
            }
        }

        return [$fields, $files];
    }
}
