<?php

class Skill
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM skills WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::connect()->query('SELECT * FROM skills ORDER BY category, name');
        return $stmt->fetchAll();
    }

    public static function allActive(): array
    {
        $stmt = Database::connect()->query('SELECT * FROM skills WHERE is_active = 1 ORDER BY category, name');
        return $stmt->fetchAll();
    }

    // Directory search -- name or category, active skills only.
    public static function search(string $term = ''): array
    {
        if ($term === '') {
            return self::allActive();
        }
        $stmt = Database::connect()->prepare(
            "SELECT * FROM skills WHERE is_active = 1 AND (name LIKE ? OR category LIKE ?) ORDER BY category, name"
        );
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM skills WHERE name = ?';
        $params = [$name];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public static function create(string $name, string $category): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('INSERT INTO skills (name, category, is_active) VALUES (?, ?, 1)');
        $stmt->execute([$name, $category]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $category): bool
    {
        $stmt = Database::connect()->prepare('UPDATE skills SET name = ?, category = ? WHERE id = ?');
        $stmt->execute([$name, $category, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function setActive(int $id, bool $active): bool
    {
        $stmt = Database::connect()->prepare('UPDATE skills SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
        return $stmt->rowCount() > 0;
    }

    // Skill categories currently in use -- populates filter dropdowns.
    public static function categories(): array
    {
        $stmt = Database::connect()->query('SELECT DISTINCT category FROM skills ORDER BY category');
        return array_column($stmt->fetchAll(), 'category');
    }

    // Most-requested skills (by session count) for the Admin stats
    // dashboard -- counts/joins only, never touches sessions.notes.
    public static function mostRequested(int $limit = 5): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT s.id, s.name, s.category, COUNT(sess.id) AS session_count
             FROM skills s
             JOIN sessions sess ON sess.skill_id = s.id
             GROUP BY s.id, s.name, s.category
             ORDER BY session_count DESC, s.name
             LIMIT ' . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
