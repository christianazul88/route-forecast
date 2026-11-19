<?php
declare(strict_types=1);

final class AuditLog
{
    public static function record(?int $userId, string $action, string $entity, ?int $id, array $details = []): void
    {
        $statement = db()->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$userId, $action, $entity, $id, json_encode($details, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]);
    }

    public static function tryRecord(?int $userId, string $action, string $entity, ?int $id, array $details = []): void
    {
        try {
            self::record($userId, $action, $entity, $id, $details);
        } catch (Throwable $error) {
            error_log('Route Forecast audit could not record ' . $action . ': ' . $error::class);
        }
    }

    public static function authentication(string $action, string $username, ?int $userId = null): void
    {
        $account = preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D', $username) ? $username : '[blank or invalid username]';
        self::tryRecord($userId, $action, 'authentication', null, ['account' => $account]);
    }

    public static function followRequest(string $page): void
    {
        if (in_array($page, ['login', 'logout'], true)) return;
        $method = $_SERVER['REQUEST_METHOD'];
        $rawId = $_GET['id'] ?? $_GET['trip_id'] ?? $_POST['id'] ?? null;
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        register_shutdown_function(static function () use ($page, $method, $id): void {
            try {
                $userId = current_user()['id'] ?? null;
                $status = http_response_code() ?: 200;
                if ($page === 'audit_event' && $status === 204) return;
                if ($userId === null || in_array($status, [401, 403], true)) $action = 'access_denied';
                elseif ($status >= 400) $action = 'request_failed';
                else $action = $method === 'GET' ? 'view' : 'submit';
                self::tryRecord($userId, $action, 'request', $id, ['page' => $page, 'http_status' => $status]);
            } catch (Throwable $error) {
                error_log('Route Forecast audit could not identify the request actor: ' . $error::class);
            }
        });
    }

    public static function recent(string $category, ?int $before): array
    {
        $conditions = [];
        $parameters = [];
        $categorySql = match ($category) {
            'auth' => "a.entity_type = 'authentication' OR a.action = 'setup_admin'",
            'changes' => "a.action IN ('archive', 'create', 'record', 'save', 'setup_admin', 'status_change')",
            'issues' => "a.action IN ('login_failed', 'login_locked', 'login_rejected', 'setup_failed', 'request_failed', 'change_rejected', 'access_denied')",
            default => null,
        };
        if ($categorySql) $conditions[] = '(' . $categorySql . ')';
        if ($before !== null) {
            $conditions[] = 'a.id < ?';
            $parameters[] = $before;
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $statement = db()->prepare('SELECT a.id, a.action, a.entity_type, a.entity_id, a.details,
            UNIX_TIMESTAMP(a.created_at) AS happened_at, u.username
            FROM audit_log a LEFT JOIN users u ON u.id = a.user_id' . $where . ' ORDER BY a.id DESC LIMIT 31');
        $statement->execute($parameters);
        return $statement->fetchAll();
    }
}
