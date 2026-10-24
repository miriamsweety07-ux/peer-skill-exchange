<?php

class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Active-only lookup -- used anywhere a Member picks another Member to
    // interact with (directory, session request), so a deactivated account
    // never shows up as a live option even though its historical rows stay
    // intact everywhere else.
    public static function findActive(int $id): ?array
    {
        $stmt = Database::connect()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allMembers(): array
    {
        $stmt = Database::connect()->query(
            "SELECT * FROM users WHERE role = 'member' ORDER BY name"
        );
        return $stmt->fetchAll();
    }

    public static function create(string $name, string $email, string $password, string $role): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, "active")'
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int)$pdo->lastInsertId();
    }

    public static function setStatus(int $id, string $status): bool
    {
        $stmt = Database::connect()->prepare('UPDATE users SET status = ? WHERE id = ? AND role = "member"');
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function updateBio(int $id, ?string $bio): void
    {
        $stmt = Database::connect()->prepare('UPDATE users SET bio = ? WHERE id = ?');
        $stmt->execute([$bio, $id]);
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = ?';
        $params = [$email];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public static function countActiveMembers(): int
    {
        $stmt = Database::connect()->query("SELECT COUNT(*) c FROM users WHERE role = 'member' AND status = 'active'");
        return (int)$stmt->fetch()['c'];
    }

    public static function countAllMembers(): int
    {
        $stmt = Database::connect()->query("SELECT COUNT(*) c FROM users WHERE role = 'member'");
        return (int)$stmt->fetch()['c'];
    }
}
