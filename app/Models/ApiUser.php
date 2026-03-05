<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ApiUser
{
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT * FROM api_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
