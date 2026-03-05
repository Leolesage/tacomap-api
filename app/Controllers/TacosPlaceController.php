<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Mailer;
use App\Core\Pdf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploads;
use App\Core\Validator;
use App\Models\TacosPlace;

final class TacosPlaceController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function index(Request $request): void
    {
        $page = (int)$request->queryParam('page', 1);
        $limit = (int)$request->queryParam('limit', 20);
        $query = trim((string)$request->queryParam('q', ''));

        if ($page < 1) {
            $page = 1;
        }
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $result = TacosPlace::paginate($page, $limit, $query);
        $items = [];
        foreach ($result['data'] as $item) {
            $items[] = $this->mapTacosPlace($item);
        }

        Response::json([
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'q' => $query,
            'hasMore' => ($page * $limit) < $result['total'],
            'total' => $result['total'],
        ], 200);
    }

    public function show(Request $request): void
    {
        $id = (int)$request->param('id');
        if ($id <= 0) {
            Response::json(['error' => 'not_found'], 404);
        }

        $item = TacosPlace::find($id);
        if ($item === null) {
            Response::json(['error' => 'not_found'], 404);
        }

        Response::json($this->mapTacosPlace($item), 200);
    }

    public function store(Request $request): void
    {
        $data = $request->body;
        $files = $request->files;

        [$errors, $clean] = Validator::validateTacosPlace($data, $files, true, $this->config['uploads']);
        if (!empty($errors)) {
            Response::json(['error' => 'validation_error', 'fields' => $errors], 400);
        }

        $photoPath = $this->savePhoto($files['photo']);
        $now = date('Y-m-d H:i:s');

        $clean['photo'] = $photoPath;
        $clean['created_at'] = $now;
        $clean['updated_at'] = $now;

        $item = TacosPlace::create($clean);
        $this->sendCreationEmail($item);

        Response::json($this->mapTacosPlace($item), 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        if ($id <= 0) {
            Response::json(['error' => 'not_found'], 404);
        }

        $existing = TacosPlace::find($id);
        if ($existing === null) {
            Response::json(['error' => 'not_found'], 404);
        }

        $data = $request->body;
        $files = $request->files;

        $merged = [
            'name' => $data['name'] ?? $existing['name'],
            'description' => $data['description'] ?? $existing['description'],
            'date' => $data['date'] ?? $existing['date'],
            'price' => $data['price'] ?? $existing['price'],
            'latitude' => $data['latitude'] ?? $existing['latitude'],
            'longitude' => $data['longitude'] ?? $existing['longitude'],
            'contact_name' => $data['contact_name'] ?? $existing['contact_name'],
            'contact_email' => $data['contact_email'] ?? $existing['contact_email'],
        ];

        [$errors, $clean] = Validator::validateTacosPlace($merged, $files, false, $this->config['uploads']);
        if (!empty($errors)) {
            Response::json(['error' => 'validation_error', 'fields' => $errors], 400);
        }

        $photoPath = $existing['photo'];
        if (!empty($files['photo'])) {
            $photoPath = $this->savePhoto($files['photo']);
            $this->deletePhoto($existing['photo']);
        }

        $clean['photo'] = $photoPath;
        $clean['updated_at'] = date('Y-m-d H:i:s');

        $item = TacosPlace::update($id, $clean);
        if ($item === null) {
            Response::json(['error' => 'not_found'], 404);
        }
        Response::json($this->mapTacosPlace($item), 200);
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        if ($id <= 0) {
            Response::json(['error' => 'not_found'], 404);
        }

        $item = TacosPlace::find($id);
        if ($item === null) {
            Response::json(['error' => 'not_found'], 404);
        }

        $deleted = TacosPlace::delete($id);
        if ($deleted) {
            $this->deletePhoto($item['photo']);
        }

        Response::json(['deleted' => true], 200);
    }

    public function pdf(Request $request): void
    {
        $id = (int)$request->param('id');
        if ($id <= 0) {
            Response::json(['error' => 'not_found'], 404);
        }

        $item = TacosPlace::find($id);
        if ($item === null) {
            Response::json(['error' => 'not_found'], 404);
        }

        $mapped = $this->mapTacosPlace($item);
        $lines = [
            'TacoMap France - TacosPlace Detail',
            'ID: ' . $mapped['id'],
            'Name: ' . $mapped['name'],
            'Description: ' . $mapped['description'],
            'Date: ' . $mapped['date'],
            'Price: ' . $mapped['price'],
            'Latitude: ' . $mapped['latitude'],
            'Longitude: ' . $mapped['longitude'],
            'Contact Name: ' . $mapped['contact_name'],
            'Contact Email: ' . $mapped['contact_email'],
            'Photo: ' . $mapped['photo_url'],
            'Created At: ' . $mapped['created_at'],
            'Updated At: ' . $mapped['updated_at'],
        ];

        Pdf::download($lines, 'tacos-place-' . $mapped['id'] . '.pdf');
    }

    private function mapTacosPlace(array $item): array
    {
        if (empty($item)) {
            return [];
        }

        return [
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'description' => $item['description'],
            'date' => $item['date'],
            'price' => (int)$item['price'],
            'latitude' => (float)$item['latitude'],
            'longitude' => (float)$item['longitude'],
            'contact_name' => $item['contact_name'],
            'contact_email' => $item['contact_email'],
            'photo' => $item['photo'],
            'photo_url' => $this->publicUrl($item['photo']),
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
        ];
    }

    private function publicUrl(string $path): string
    {
        $base = rtrim($this->config['app']['url'], '/');
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }

    private function savePhoto(array $file): string
    {
        $validationError = Uploads::validatePhoto($file, $this->config['uploads']);
        if ($validationError !== null) {
            Response::json(['error' => 'validation_error', 'fields' => ['photo' => $validationError]], 400);
        }

        try {
            return Uploads::save($file, $this->publicPath());
        } catch (\RuntimeException $e) {
            Response::json(['error' => 'upload_failed'], 500);
        }
        return '';
    }

    private function deletePhoto(string $relativePath): void
    {
        $relativePath = ltrim($relativePath, '/');
        if (!str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        $fullPath = $this->publicPath() . DIRECTORY_SEPARATOR . $relativePath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function publicPath(): string
    {
        return realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
    }

    private function sendCreationEmail(array $item): void
    {
        $mapped = $this->mapTacosPlace($item);
        $detailLink = $this->detailLink((int)$mapped['id']);

        $lines = [
            'A new TacosPlace has been created on TacoMap France.',
            '',
            'Name: ' . $mapped['name'],
            'Description: ' . $mapped['description'],
            'Date: ' . $mapped['date'],
            'Price: ' . $mapped['price'],
            'Latitude: ' . $mapped['latitude'],
            'Longitude: ' . $mapped['longitude'],
            'Contact Name: ' . $mapped['contact_name'],
            'Contact Email: ' . $mapped['contact_email'],
            'Photo URL: ' . $mapped['photo_url'],
            '',
            'Detail link: ' . $detailLink,
        ];

        $mailer = new Mailer($this->config['smtp']);
        $mailer->send((string)$mapped['contact_email'], 'TacoMap France - TacosPlace Created', implode("\n", $lines));
    }

    private function detailLink(int $id): string
    {
        $adminBase = trim((string)($this->config['app']['admin_detail_base_url'] ?? ''));
        if ($adminBase !== '') {
            return rtrim($adminBase, '/') . '/' . $id;
        }

        return rtrim($this->config['app']['url'], '/') . '/api/tacos-places/' . $id;
    }
}
