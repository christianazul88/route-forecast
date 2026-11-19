<?php
declare(strict_types=1);

final class BoxType
{
    public const SHAPES = [
        'Box' => 'Rectangular box',
        'Cylinder' => 'Upright cylinder',
        'Irregular' => 'Irregular or wrapped item',
    ];

    public static function active(): array
    {
        return db()->query('SELECT id, code, name, shape, length_cm, width_cm, height_cm, weight_kg, can_tilt FROM box_types WHERE is_active = 1 ORDER BY name, code')->fetchAll();
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM box_types ORDER BY is_active DESC, name, code')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $statement = db()->prepare('SELECT * FROM box_types WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public static function save(array $data, ?int $id): int
    {
        return db_transaction(static function (PDO $pdo) use ($data, $id): int {
            $fields = [$data['code'], $data['name'], $data['shape'], $data['length_cm'], $data['width_cm'], $data['height_cm'], $data['weight_kg'], $data['can_tilt'], $data['is_active']];
            if ($id === null) {
                $statement = $pdo->prepare('INSERT INTO box_types (code, name, shape, length_cm, width_cm, height_cm, weight_kg, can_tilt, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($fields);
                $id = (int) $pdo->lastInsertId();
            } else {
                $statement = $pdo->prepare('UPDATE box_types SET code = ?, name = ?, shape = ?, length_cm = ?, width_cm = ?, height_cm = ?, weight_kg = ?, can_tilt = ?, is_active = ? WHERE id = ?');
                $statement->execute([...$fields, $id]);
            }
            audit('save', 'box_type', $id, ['code' => $data['code']]);
            return $id;
        });
    }

    public static function archive(int $id): void
    {
        db_transaction(static function (PDO $pdo) use ($id): void {
            $statement = $pdo->prepare('UPDATE box_types SET is_active = 0 WHERE id = ?');
            $statement->execute([$id]);
            if ($statement->rowCount() === 0) {
                throw new RuntimeException('Box type not found or already inactive.');
            }
            audit('archive', 'box_type', $id);
        });
    }
}
