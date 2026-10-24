<?php

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && !empty($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireValid(): void
    {
        $token = $_POST['csrf_token'] ?? null;

        if (!self::verify($token)) {
            http_response_code(419);
            require __DIR__ . '/../app/views/errors/500.php';
            exit;
        }
    }
}
