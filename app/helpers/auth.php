<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $checked = false;
    if (empty($_SESSION['user'])) {
        return null;
    }
    if (!$checked) {
        $checked = true;
        $user = User::findById((int) $_SESSION['user']['id']);
        if (!$user || !$user['is_active']) {
            unset($_SESSION['user']);
            return null;
        }
        $_SESSION['user'] = ['id' => (int) $user['id'], 'full_name' => $user['full_name'], 'role' => $user['role']];
    }
    return $_SESSION['user'];
}

function require_login(): void
{
    if (current_user() === null) {
        flash('warning', 'Please sign in to continue.');
        redirect('login');
    }
}

function require_admin(string $message = 'Only admins can change masterfile records.'): void
{
    require_login();
    if (current_user()['role'] !== 'Admin') {
        http_response_code(403);
        throw new RuntimeException($message);
    }
}

function attempt_login(string $username, string $password): bool
{
    $user = User::findByUsername($username);
    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ];
    unset($_SESSION['csrf_token'], $_SESSION['login_messages']);
    $messageUsername = (string) config('LOGIN_MESSAGE_USERNAME', '');
    $firstMessage = trim((string) config('LOGIN_MESSAGE_FIRST', ''));
    $secondMessage = trim((string) config('LOGIN_MESSAGE_SECOND', ''));
    if ($firstMessage !== '' && $secondMessage !== '' && strcasecmp($user['username'], $messageUsername) === 0) {
        $_SESSION['login_messages'] = [$firstMessage, $secondMessage];
    }
    return true;
}

function sign_out(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}
