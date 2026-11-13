<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = config('DB_HOST', '127.0.0.1');
        $port = config('DB_PORT', '3306');
        $name = config('DB_NAME', 'route_forecast');
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            config('DB_USER', 'root'),
            config('DB_PASS', ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'dashboard', array $parameters = []): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/route-forecast/public/index.php'), '/\\');
    return $base . '/index.php?' . http_build_query(['page' => $page] + $parameters);
}

function asset(string $path): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/route-forecast/public/index.php'), '/\\');
    return $base . '/assets/' . ltrim($path, '/');
}

function redirect(string $page = 'dashboard', array $parameters = []): never
{
    header('Location: ' . url($page, $parameters), true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        throw new RuntimeException('Your form expired. Refresh the page and try again.');
    }
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('This action requires a form submission.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function render(string $view, array $data = []): void
{
    $page = (string) ($_GET['page'] ?? 'branches');
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/views/layout/header.php';
    require APP_ROOT . '/app/views/' . $view . '.php';
    require APP_ROOT . '/app/views/layout/footer.php';
}

function audit(string $action, string $entityType, ?int $entityId, array $details = []): void
{
    $statement = db()->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([current_user()['id'] ?? null, $action, $entityType, $entityId, json_encode($details, JSON_THROW_ON_ERROR)]);
}

function positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) {
        http_response_code(400);
        throw new InvalidArgumentException('Invalid record ID.');
    }
    return $id;
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Planned' => 'text-bg-primary',
        'Dispatched' => 'text-bg-info',
        'Completed' => 'text-bg-success',
        'Cancelled' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function csv_safe(?string $value): string
{
    $value = $value ?? '';
    return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
}
