<?php
declare(strict_types=1);

final class Branch
{
    public static function active(): array
    {
        return db()->query('SELECT id, code, name, address, lat, lng, is_origin FROM branches WHERE is_active = 1 ORDER BY is_origin DESC, name')->fetchAll();
    }

    public static function activeByIds(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = db()->prepare("SELECT id, code, name, address, lat, lng, is_origin FROM branches WHERE is_active = 1 AND id IN ({$placeholders})");
        $statement->execute($ids);
        $rows = [];
        foreach ($statement->fetchAll() as $branch) {
            $rows[(int) $branch['id']] = $branch;
        }
        return $rows;
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM branches ORDER BY is_origin DESC, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $statement = db()->prepare('SELECT * FROM branches WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public static function save(array $data, ?int $id): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($data['is_origin']) {
                $pdo->prepare('UPDATE branches SET is_origin = 0 WHERE is_origin = 1')->execute();
            }
            $fields = [$data['code'], $data['name'], $data['address'], $data['lat'], $data['lng'],
                $data['contact_person'], $data['contact_no'], $data['is_active'], $data['is_origin']];
            if ($id === null) {
                $statement = $pdo->prepare('INSERT INTO branches (code, name, address, lat, lng, contact_person, contact_no, is_active, is_origin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute($fields);
                $id = (int) $pdo->lastInsertId();
            } else {
                $statement = $pdo->prepare('UPDATE branches SET code = ?, name = ?, address = ?, lat = ?, lng = ?, contact_person = ?, contact_no = ?, is_active = ?, is_origin = ? WHERE id = ?');
                $statement->execute([...$fields, $id]);
            }
            audit('save', 'branch', $id, ['code' => $data['code']]);
            $pdo->commit();
            return $id;
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function archive(int $id): void
    {
        db_transaction(static function (PDO $pdo) use ($id): void {
            $statement = $pdo->prepare('UPDATE branches SET is_active = 0 WHERE id = ? AND is_origin = 0');
            $statement->execute([$id]);
            if ($statement->rowCount() === 0) {
                throw new RuntimeException('The origin warehouse cannot be archived.');
            }
            audit('archive', 'branch', $id);
        });
    }
}
