<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require __DIR__ . '/../config/bootstrap.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php database/create_admin.php USERNAME FULL_NAME PASSWORD\n");
    exit(1);
}
[$script, $username, $fullName, $password] = $argv;
if (strlen($username) < 3 || strlen($password) < 12 || trim($fullName) === '') {
    fwrite(STDERR, "Username must be 3+ characters, name is required, and password must be 12+ characters.\n");
    exit(1);
}

$statement = db()->prepare('INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)');
$statement->execute([trim($fullName), trim($username), password_hash($password, PASSWORD_DEFAULT), 'Admin']);
fwrite(STDOUT, "Admin account created.\n");
