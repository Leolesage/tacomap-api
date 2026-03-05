<?php
declare(strict_types=1);

namespace App\Core;

final class Uploads
{
    public static function validatePhoto(array $file, array $config): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return 'upload_error';
        }

        $size = (int)($file['size'] ?? 0);
        $maxSize = (int)($config['max_size_bytes'] ?? 3145728);
        if ($size < 1 || $size > $maxSize) {
            return 'too_large';
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_file($tmp)) {
            return 'invalid';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $allowedMimes = $config['allowed_mimes'] ?? ['image/jpeg', 'image/png', 'image/webp'];
        if (!is_array($allowedMimes)) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        }

        if (!in_array($mime, $allowedMimes, true)) {
            return 'invalid_type';
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = $config['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];
        if (!is_array($allowedExtensions)) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        }
        $allowedExtensions = array_map('strtolower', $allowedExtensions);

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            return 'invalid_extension';
        }

        if (!self::isMimeExtensionConsistent($mime, $extension)) {
            return 'invalid_extension';
        }

        return null;
    }

    public static function save(array $file, string $publicDir): string
    {
        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_file($tmp)) {
            throw new \RuntimeException('upload_failed');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = rtrim($publicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $destination = $uploadDir . DIRECTORY_SEPARATOR . $name;
        if (!self::moveFile($tmp, $destination)) {
            throw new \RuntimeException('upload_failed');
        }

        return 'uploads/' . $name;
    }

    public static function delete(string $relativePath, string $publicDir): bool
    {
        $relativePath = ltrim($relativePath, '/');
        $file = basename($relativePath);
        if ($file === '') {
            return false;
        }
        $fullPath = rtrim($publicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $file;
        if (!is_file($fullPath)) {
            return false;
        }
        return @unlink($fullPath);
    }

    private static function moveFile(string $from, string $to): bool
    {
        if (is_uploaded_file($from)) {
            return move_uploaded_file($from, $to);
        }
        return rename($from, $to);
    }

    private static function isMimeExtensionConsistent(string $mime, string $extension): bool
    {
        return match ($mime) {
            'image/jpeg' => in_array($extension, ['jpg', 'jpeg'], true),
            'image/png' => $extension === 'png',
            'image/webp' => $extension === 'webp',
            default => false,
        };
    }
}
