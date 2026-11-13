<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
date_default_timezone_set('Asia/Manila');

$defaultEnvFile = PHP_OS_FAMILY === 'Windows'
    ? 'C:\\xampp\\route-forecast.env'
    : APP_ROOT . '/config/.env';
$envFile = getenv('ROUTE_FORECAST_ENV') ?: $defaultEnvFile;
$env = is_file($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : [];
if ($env === false) {
    throw new RuntimeException('Cannot read Route Forecast environment file.');
}

function config(string $key, ?string $default = null): ?string
{
    global $env;
    $value = getenv($key);
    if ($value !== false) {
        return (string) $value;
    }
    return isset($env[$key]) ? (string) $env[$key] : $default;
}

spl_autoload_register(static function (string $class): void {
    $directories = [APP_ROOT . '/app/models/', APP_ROOT . '/app/controllers/'];
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once APP_ROOT . '/app/helpers/functions.php';
require_once APP_ROOT . '/app/helpers/auth.php';

if (PHP_SAPI !== 'cli') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('route_forecast_session');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}
