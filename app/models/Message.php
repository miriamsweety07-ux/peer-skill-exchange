<?php

// Every query here is scoped by session_id -- the CALLER is responsible
// for having already verified the requesting user is a participant via
// Session::findForParticipant() before calling any of these (both
// MemberController's chat actions do this before touching Message at
// all), so a non-participant's forged request never even reaches this
// model layer with a session id that resolves to anything.
class Message
{
    public static function forSession(int $sessionId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT m.*, u.name AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.session_id = ?
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    }

    // Messages after a given id -- used by the AJAX poll to fetch only
    // what's new since the browser's last check.
    public static function forSessionSince(int $sessionId, int $afterId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT m.*, u.name AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.session_id = ? AND m.id > ?
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->execute([$sessionId, $afterId]);
        return $stmt->fetchAll();
    }

    public static function send(int $sessionId, int $senderId, string $body): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('INSERT INTO messages (session_id, sender_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$sessionId, $senderId, $body]);
        return (int)$pdo->lastInsertId();
    }
}
