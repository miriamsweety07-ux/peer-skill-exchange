<?php

// One row per (member, skill, teach/learn direction). Owns the matching
// query too, since matching is just a join across this table with itself
// (FEATURES.md §3 "Matching is computed at query time, not stored").
class MemberSkill
{
    private const TYPES = ['teach', 'learn'];
    private const LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];

    public static function types(): array
    {
        return self::TYPES;
    }

    public static function levels(): array
    {
        return self::LEVELS;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM member_skills WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Scoped to the owning member -- used before every delete so a Member
    // can't remove someone else's skill row by guessing its id.
    public static function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM member_skills WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forUser(int $userId, string $type): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT ms.*, s.name AS skill_name, s.category AS skill_category, s.is_active AS skill_active
             FROM member_skills ms
             JOIN skills s ON s.id = ms.skill_id
             WHERE ms.user_id = ? AND ms.type = ?
             ORDER BY s.category, s.name'
        );
        $stmt->execute([$userId, $type]);
        return $stmt->fetchAll();
    }

    public static function exists(int $userId, int $skillId, string $type): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT id FROM member_skills WHERE user_id = ? AND skill_id = ? AND type = ?'
        );
        $stmt->execute([$userId, $skillId, $type]);
        return (bool)$stmt->fetch();
    }

    public static function add(int $userId, int $skillId, string $type, ?string $level): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO member_skills (user_id, skill_id, type, proficiency_level) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $skillId, $type, $type === 'teach' ? $level : null]);
        return (int)$pdo->lastInsertId();
    }

    public static function deleteOwned(int $id, int $userId): bool
    {
        $stmt = Database::connect()->prepare('DELETE FROM member_skills WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // Every active member who TEACHes a given skill, with their self-rated
    // level -- feeds the Skill Directory's per-skill teacher list.
    public static function teachersOf(int $skillId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT ms.proficiency_level, u.id AS user_id, u.name, u.bio
             FROM member_skills ms
             JOIN users u ON u.id = ms.user_id
             WHERE ms.skill_id = ? AND ms.type = 'teach' AND u.status = 'active'
             ORDER BY u.name"
        );
        $stmt->execute([$skillId]);
        return $stmt->fetchAll();
    }

    // Live matching query (FEATURES.md §3): every active member who
    // teaches something this member wants to learn, with the skill they'd
    // teach and whether the match is mutual (this member also teaches
    // something that other member wants). Never cached -- recomputed on
    // every call straight from member_skills.
    public static function matchesFor(int $userId): array
    {
        $pdo = Database::connect();

        // want(me) x teach(other)
        $stmt = $pdo->prepare(
            "SELECT other.id AS user_id, other.name, other.bio,
                    sk.id AS skill_id, sk.name AS skill_name, sk.category AS skill_category,
                    teach.proficiency_level
             FROM member_skills want
             JOIN skills sk ON sk.id = want.skill_id AND sk.is_active = 1
             JOIN member_skills teach ON teach.skill_id = want.skill_id AND teach.type = 'teach' AND teach.user_id != ?
             JOIN users other ON other.id = teach.user_id AND other.status = 'active'
             WHERE want.user_id = ? AND want.type = 'learn'
             ORDER BY other.name, sk.name"
        );
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return [];
        }

        // Reverse direction, grouped by the other member: does that member
        // want anything THIS member teaches? Used only to set the mutual
        // flag, never to add rows of its own.
        $stmt2 = $pdo->prepare(
            "SELECT DISTINCT other_want.user_id
             FROM member_skills my_teach
             JOIN member_skills other_want
                  ON other_want.skill_id = my_teach.skill_id AND other_want.type = 'learn'
             WHERE my_teach.user_id = ? AND my_teach.type = 'teach'"
        );
        $stmt2->execute([$userId]);
        $mutualIds = array_column($stmt2->fetchAll(), 'user_id');
        $mutualIds = array_map('intval', $mutualIds);

        foreach ($rows as &$row) {
            $row['mutual'] = in_array((int)$row['user_id'], $mutualIds, true);
        }
        unset($row);

        return $rows;
    }

    // Whether this skill still has any member_skills history -- informs
    // the Admin UI (not a hard delete guard, since we never hard-delete a
    // skill at all, only deactivate).
    public static function skillHasMembers(int $skillId): bool
    {
        $stmt = Database::connect()->prepare('SELECT id FROM member_skills WHERE skill_id = ? LIMIT 1');
        $stmt->execute([$skillId]);
        return (bool)$stmt->fetch();
    }
}
