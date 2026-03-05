<?php
declare(strict_types=1);

namespace App\Core;

final class AuthMiddleware
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function handle(Request $request): bool
    {
        $auth = $request->header('authorization');
        if (!$auth || !preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            Response::json(['error' => 'unauthorized'], 401);
            return false;
        }

        $payload = Jwt::decode($matches[1], $this->secret);
        if ($payload === null) {
            Response::json(['error' => 'unauthorized'], 401);
            return false;
        }

        $request->setUser($payload);
        return true;
    }
}
