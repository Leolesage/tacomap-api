<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class TileController
{
    public function show(Request $request): void
    {
        $z = (int)$request->param('z');
        $x = (int)$request->param('x');
        $y = (int)$request->param('y');

        if ($z < 0 || $x < 0 || $y < 0) {
            Response::json(['error' => 'not_found'], 404);
        }

        $url = sprintf('https://tile.openstreetmap.org/%d/%d/%d.png', $z, $x, $y);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "User-Agent: tacomap-api tile proxy\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);
        $status = 0;
        $contentType = '';
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
                    $status = (int)$m[1];
                }
                if (stripos($header, 'Content-Type:') === 0) {
                    $contentType = trim(substr($header, strlen('Content-Type:')));
                }
            }
        }

        if ($data === false || $status !== 200) {
            Response::json(['error' => 'tile_unavailable'], 502);
        }

        header('Content-Type: ' . ($contentType !== '' ? $contentType : 'image/png'));
        header('Cache-Control: public, max-age=86400');
        echo $data;
        exit;
    }
}
