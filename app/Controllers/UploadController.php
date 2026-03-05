<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Uploads;

final class UploadController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function upload(Request $request): void
    {
        $file = $request->files['photo'] ?? null;
        if ($file === null) {
            Response::json(['error' => 'validation_error', 'fields' => ['photo' => 'required']], 400);
        }

        $error = Uploads::validatePhoto($file, $this->config['uploads']);
        if ($error !== null) {
            Response::json(['error' => 'validation_error', 'fields' => ['photo' => $error]], 400);
        }

        $publicDir = realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
        try {
            $path = Uploads::save($file, $publicDir);
        } catch (\RuntimeException $e) {
            Response::json(['error' => 'upload_failed'], 500);
        }

        $url = rtrim($this->config['app']['url'], '/') . '/' . ltrim($path, '/');
        Response::json([
            'photo' => $path,
            'photo_url' => $url,
        ], 201);
    }

    public function delete(Request $request): void
    {
        $name = (string)$request->param('name', '');
        $file = basename($name);
        if ($file === '') {
            Response::json(['error' => 'not_found'], 404);
        }

        $publicDir = realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
        $deleted = Uploads::delete('uploads/' . $file, $publicDir);
        if (!$deleted) {
            Response::json(['error' => 'not_found'], 404);
        }

        Response::json(['deleted' => true], 200);
    }
}
