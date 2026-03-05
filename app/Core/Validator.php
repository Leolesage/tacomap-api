<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function validateTacosPlace(array $data, array $files, bool $requirePhoto, array $uploadConfig): array
    {
        $errors = [];
        $clean = [];

        $required = [
            'name',
            'description',
            'date',
            'price',
            'latitude',
            'longitude',
            'contact_name',
            'contact_email',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || trim((string)$data[$field]) === '') {
                $errors[$field] = 'required';
            }
        }

        if (isset($data['name'])) {
            $clean['name'] = self::sanitizeText((string)$data['name']);
            if ($clean['name'] === '') {
                $errors['name'] = 'required';
            } elseif (mb_strlen($clean['name']) > 255) {
                $errors['name'] = 'max_length';
            }
        }

        if (isset($data['description'])) {
            $clean['description'] = self::sanitizeText((string)$data['description'], true);
            if ($clean['description'] === '') {
                $errors['description'] = 'required';
            } elseif (mb_strlen($clean['description']) > 5000) {
                $errors['description'] = 'max_length';
            }
        }

        if (isset($data['date'])) {
            $parsed = self::parseDate((string)$data['date']);
            if ($parsed === null) {
                $errors['date'] = 'invalid';
            } else {
                $clean['date'] = $parsed;
            }
        }

        if (isset($data['price'])) {
            $price = filter_var($data['price'], FILTER_VALIDATE_INT);
            if ($price === false) {
                $errors['price'] = 'invalid';
            } else {
                $clean['price'] = $price;
            }
        }

        if (isset($data['latitude'])) {
            $lat = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lat < -90 || $lat > 90) {
                $errors['latitude'] = 'invalid';
            } else {
                $clean['latitude'] = $lat;
            }
        }

        if (isset($data['longitude'])) {
            $lon = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
            if ($lon === false || $lon < -180 || $lon > 180) {
                $errors['longitude'] = 'invalid';
            } else {
                $clean['longitude'] = $lon;
            }
        }

        if (isset($data['contact_name'])) {
            $clean['contact_name'] = self::sanitizeText((string)$data['contact_name']);
            if ($clean['contact_name'] === '') {
                $errors['contact_name'] = 'required';
            } elseif (mb_strlen($clean['contact_name']) > 255) {
                $errors['contact_name'] = 'max_length';
            }
        }

        if (isset($data['contact_email'])) {
            $email = trim((string)$data['contact_email']);
            if ($email === '' || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['contact_email'] = 'invalid';
            } else {
                $clean['contact_email'] = $email;
            }
        }

        if ($requirePhoto && empty($files['photo'])) {
            $errors['photo'] = 'required';
        }

        if (!empty($files['photo'])) {
            $photoError = Uploads::validatePhoto($files['photo'], $uploadConfig);
            if ($photoError !== null) {
                $errors['photo'] = $photoError;
            }
        }

        return [$errors, $clean];
    }

    public static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($dt instanceof \DateTime && $dt->format('Y-m-d H:i:s') === $value) {
            return $dt->format('Y-m-d H:i:s');
        }

        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function sanitizeText(string $value, bool $allowLineBreaks = false): string
    {
        $value = trim($value);
        $value = strip_tags($value);

        if ($allowLineBreaks) {
            $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? $value;
        }

        return trim($value);
    }
}
