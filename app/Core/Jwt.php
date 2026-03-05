<?php
declare(strict_types=1);

namespace App\Core;

final class Jwt
{
    public static function encode(array $payload, string $secret, int $expSeconds): string
    {
        $now = time();
        if (!isset($payload['iat'])) {
            $payload['iat'] = $now;
        }
        if (!isset($payload['exp'])) {
            $payload['exp'] = $now + $expSeconds;
        }

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            self::b64urlEncode(json_encode($header)),
            self::b64urlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::b64urlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $jwt, string $secret): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode(self::b64urlDecode($header64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $signature = self::b64urlDecode($signature64);
        $expected = hash_hmac('sha256', $header64 . '.' . $payload64, $secret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::b64urlDecode($payload64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && time() >= (int)$payload['exp']) {
            return null;
        }

        return $payload;
    }

    private static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
