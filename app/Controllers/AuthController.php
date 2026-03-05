<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Jwt;
use App\Models\ApiUser;

final class AuthController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function login(Request $request): void
    {
        $data = $request->json();

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'invalid';
        }
        if ($password === '') {
            $errors['password'] = 'required';
        }

        if (!empty($errors)) {
            Response::json(['error' => 'validation_error', 'fields' => $errors], 400);
        }

        $user = ApiUser::findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            Response::json(['error' => 'invalid_credentials'], 401);
        }

        $payload = [
            'sub' => (int)$user['id'],
            'email' => $user['email'],
        ];

        $token = Jwt::encode($payload, $this->config['jwt']['secret'], (int)$this->config['jwt']['exp']);

        Response::json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int)$this->config['jwt']['exp'],
        ], 200);
    }
}
