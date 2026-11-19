<?php
declare(strict_types=1);

final class User
{
    public static function findById(int $id): ?array
    {
        $statement = db()->prepare('SELECT id, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $statement = db()->prepare('SELECT id, username, full_name, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        return $statement->fetch() ?: null;
    }
}
