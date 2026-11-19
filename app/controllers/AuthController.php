<?php
declare(strict_types=1);

final class AuthController
{
    public static function login(): void
    {
        if (current_user()) {
            redirect();
        }
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            try {
                verify_csrf();
            } catch (RuntimeException $error) {
                AuditLog::authentication('login_rejected', $username);
                throw $error;
            }
            $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
            if ($lockedUntil > time()) {
                AuditLog::authentication('login_locked', $username);
                $error = 'Too many attempts. Try again in a few minutes.';
            } elseif (attempt_login($username, $password)) {
                AuditLog::authentication('login_success', $username, current_user()['id']);
                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                redirect();
            } else {
                AuditLog::authentication('login_failed', $username);
                $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 300;
                    $_SESSION['login_attempts'] = 0;
                }
                $error = 'Invalid username or password.';
            }
        }
        render('login', ['title' => 'Sign in', 'error' => $error]);
    }

    public static function logout(): void
    {
        try {
            require_post();
            verify_csrf();
        } catch (RuntimeException $error) {
            AuditLog::tryRecord(current_user()['id'] ?? null, 'request_failed', 'request', null, ['page' => 'logout', 'http_status' => http_response_code()]);
            throw $error;
        }
        AuditLog::tryRecord(current_user()['id'] ?? null, 'logout', 'authentication', null);
        sign_out();
        redirect('login');
    }
}
