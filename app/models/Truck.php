<?php
declare(strict_types=1);

final class Truck
{
    public const BODY_TYPES = ['Ordinary dropside', 'Aluminum dropside', 'Wing van', 'Closed van', 'Reefer van'];
    public const BODY_LENGTHS_FT = [10, 12, 14, 16, 18, 20];
    public const CM_PER_FOOT = 30.48;

    public static function openings(array $truck): array
    {
        if (isset($truck['openings'])) return $truck['openings'];
        if (isset($truck['openings_json'])) {
            return json_decode($truck['openings_json'], true, 16, JSON_THROW_ON_ERROR);
        }
        if (!isset($truck['door_width_cm'], $truck['door_height_cm'], $truck['cargo_width_cm'])) return [];
        return [[
            'side' => 'rear',
            'offset' => max(0, round(((float) $truck['cargo_width_cm'] - (float) $truck['door_width_cm']) / 2, 2)),
            'width' => (float) $truck['door_width_cm'],
            'sill' => 0,
            'height' => (float) $truck['door_height_cm'],
        ]];
    }

    public static function loadProfile(array $truck): array
    {
        $profile = [
            'id' => (int) $truck['id'], 'plate' => $truck['plate_no'], 'name' => $truck['name'],
            'bodyType' => $truck['body_type'], 'capacity' => (float) $truck['capacity_kg'],
            'openings' => self::openings($truck), 'confirmed' => isset($truck['openings_json']),
            'demo' => str_starts_with($truck['plate_no'], 'DEMO-'),
        ];
        foreach (['length', 'width', 'height'] as $axis) {
            $field = "cargo_{$axis}_cm";
            $profile[$axis] = isset($truck[$field]) ? (float) $truck[$field] : null;
        }
        return $profile;
    }

    public static function active(): array
    {
        return db()->query('SELECT * FROM trucks WHERE is_active = 1 ORDER BY plate_no')->fetchAll();
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM trucks ORDER BY is_active DESC, plate_no')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $statement = db()->prepare('SELECT * FROM trucks WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public static function save(array $data, ?int $id): int
    {
        $fields = [
            $data['plate_no'], $data['name'], $data['body_type'], $data['capacity_kg'],
            ($data['diesel_km_per_liter'] ?? '') === '' ? null : $data['diesel_km_per_liter'],
            $data['cargo_length_cm'], $data['cargo_width_cm'], $data['cargo_height_cm'],
            json_encode($data['openings'], JSON_THROW_ON_ERROR), $data['is_active'],
        ];
        if ($id === null) {
            $statement = db()->prepare('INSERT INTO trucks (plate_no, name, body_type, capacity_kg, diesel_km_per_liter, cargo_length_cm, cargo_width_cm, cargo_height_cm, openings_json, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute($fields);
            $id = (int) db()->lastInsertId();
        } else {
            $statement = db()->prepare('UPDATE trucks SET plate_no = ?, name = ?, body_type = ?, capacity_kg = ?, diesel_km_per_liter = ?, cargo_length_cm = ?, cargo_width_cm = ?, cargo_height_cm = ?, openings_json = ?, is_active = ? WHERE id = ?');
            $statement->execute([...$fields, $id]);
        }
        audit('save', 'truck', $id, ['plate_no' => $data['plate_no']]);
        return $id;
    }

    public static function archive(int $id): void
    {
        $statement = db()->prepare('UPDATE trucks SET is_active = 0 WHERE id = ?');
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            throw new RuntimeException('Truck not found or already inactive.');
        }
        audit('archive', 'truck', $id);
    }
}
