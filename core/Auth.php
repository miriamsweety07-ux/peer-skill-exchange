<?php

require_once __DIR__ . '/Database.php';

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function name(): ?string
    {
        return $_SESSION['user_name'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'super_admin';
    }

    // Re-reads the account status from the database on EVERY protected
    // request, not just at login. Checking it only in attempt() meant an
    // admin deactivating a Member mid-session had no effect until that
    // Member happened to log out -- they kept requesting sessions,
    // accepting, and chatting with a deactivated account for as long as
    // their PHP session lived.
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login');
        }

        $stmt = Database::connect()->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([self::id()]);
        $row = $stmt->fetch();

        if (!$row || $row['status'] !== 'active') {
            $_SESSION = [];
            session_regenerate_id(true);
            flash_set('error', 'Your account has been deactivated. Contact the platform admin if you think this is a mistake.');
            redirect('login');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            require __DIR__ . '/../app/views/errors/404.php';
            exit;
        }
    }

    public static function dashboardRoute(string $role): string
    {
        return match ($role) {
            'super_admin' => 'admin/dashboard',
            'member'      => 'member/dashboard',
            default       => 'login',
        };
    }
}
