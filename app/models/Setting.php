<?php
declare(strict_types=1);

final class Setting
{
    public static function number(string $key, float $default): float
    {
        $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $value !== false && is_numeric($value) ? (float) $value : $default;
    }
}
