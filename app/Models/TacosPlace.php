<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class TacosPlace
{
    public static function paginate(int $page, int $limit, string $query = ''): array
    {
        $pdo = Database::get();
        $params = [];
        $whereSql = '';
        if ($query !== '') {
            $whereSql = ' WHERE name LIKE :query OR contact_email LIKE :query ';
            $params['query'] = '%' . $query . '%';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tacos_places' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare('SELECT * FROM tacos_places' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT * FROM tacos_places WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    public static function create(array $data): array
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare(
            'INSERT INTO tacos_places (name, description, `date`, price, latitude, longitude, contact_name, contact_email, photo, created_at, updated_at)
             VALUES (:name, :description, :date, :price, :latitude, :longitude, :contact_name, :contact_email, :photo, :created_at, :updated_at)'
        );

        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'],
            'date' => $data['date'],
            'price' => $data['price'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'photo' => $data['photo'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
        ]);

        $id = (int)$pdo->lastInsertId();
        return self::find($id) ?? [];
    }

    public static function update(int $id, array $data): ?array
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare(
            'UPDATE tacos_places SET
                name = :name,
                description = :description,
                `date` = :date,
                price = :price,
                latitude = :latitude,
                longitude = :longitude,
                contact_name = :contact_name,
                contact_email = :contact_email,
                photo = :photo,
                updated_at = :updated_at
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'],
            'date' => $data['date'],
            'price' => $data['price'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'photo' => $data['photo'],
            'updated_at' => $data['updated_at'],
        ]);

        return self::find($id);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('DELETE FROM tacos_places WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
