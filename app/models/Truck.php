<?php
declare(strict_types=1);

final class Truck
{
    public const BODY_TYPES = ['Ordinary dropside', 'Aluminum dropside', 'Wing van', 'Closed van', 'Reefer van'];

    public static function active(): array
    {
        return db()->query('SELECT id, plate_no, name, body_type, capacity_kg, cargo_length_cm, cargo_width_cm, cargo_height_cm, door_width_cm, door_height_cm FROM trucks WHERE is_active = 1 ORDER BY plate_no')->fetchAll();
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
        $fields = [$data['plate_no'], $data['name'], $data['body_type'], $data['capacity_kg'], $data['cargo_length_cm'], $data['cargo_width_cm'], $data['cargo_height_cm'], $data['door_width_cm'], $data['door_height_cm'], $data['is_active']];
        if ($id === null) {
            $statement = db()->prepare('INSERT INTO trucks (plate_no, name, body_type, capacity_kg, cargo_length_cm, cargo_width_cm, cargo_height_cm, door_width_cm, door_height_cm, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute($fields);
            $id = (int) db()->lastInsertId();
        } else {
            $statement = db()->prepare('UPDATE trucks SET plate_no = ?, name = ?, body_type = ?, capacity_kg = ?, cargo_length_cm = ?, cargo_width_cm = ?, cargo_height_cm = ?, door_width_cm = ?, door_height_cm = ?, is_active = ? WHERE id = ?');
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
