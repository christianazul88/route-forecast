<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

$setupToken = (string) config('SETUP_TOKEN', '');
$userCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount !== 0 || strlen($setupToken) < 20) {
    http_response_code(404);
    exit('Setup is unavailable.');
}

$errors = [];
$values = ['username' => '', 'full_name' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $values['username'] = trim((string) ($_POST['username'] ?? ''));
        $values['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (!hash_equals($setupToken, (string) ($_POST['setup_token'] ?? ''))) {
            $errors[] = 'The setup token is incorrect.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $values['username'])) {
            $errors[] = 'Username must be 3–80 letters, numbers, dots, hyphens, or underscores.';
        }
        if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 120) {
            $errors[] = 'Enter a name of at most 120 characters.';
        }
        if (strlen($password) < 12 || $password !== $confirm) {
            $errors[] = 'Passwords must match and contain at least 12 characters.';
        }
        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'recommended_max_min' FOR UPDATE")->fetchColumn();
                if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                    throw new RuntimeException('An admin account already exists.');
                }
                $statement = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, 'Admin')");
                $statement->execute([$values['full_name'], $values['username'], password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int) $pdo->lastInsertId();
                audit('setup_admin', 'user', $id);
                $pdo->commit();
                flash('success', 'Admin account created. Sign in to continue.');
                redirect('login');
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
        }
    } catch (PDOException $error) {
        error_log((string) $error);
        $errors[] = 'Could not create the account. Check the username and database setup.';
    } catch (RuntimeException $error) {
        $errors[] = $error->getMessage();
    }
    if ($errors) {
        AuditLog::authentication('setup_failed', $values['username']);
    }
}

render('setup', ['title' => 'First admin setup', 'errors' => $errors, 'values' => $values]);
