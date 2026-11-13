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
            verify_csrf();
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
            if ($lockedUntil > time()) {
                $error = 'Too many attempts. Try again in a few minutes.';
            } elseif (attempt_login($username, $password)) {
                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                redirect();
            } else {
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
        require_post();
        verify_csrf();
        sign_out();
        redirect('login');
    }
}
