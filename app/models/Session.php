<?php

// One-on-one scheduled session between two Members for one skill. Owns the
// small explicit state machine described in FEATURES.md §3: every status
// transition is validated here, never applied directly by a controller.
class Session
{
    public const STATUSES = ['requested', 'confirmed', 'declined', 'cancelled', 'completed'];

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT sess.*, sk.name AS skill_name, sk.category AS skill_category,
                    req.name AS requester_name, rec.name AS recipient_name,
                    req.status AS requester_status, rec.status AS recipient_status
             FROM sessions sess
             JOIN skills sk ON sk.id = sess.skill_id
             JOIN users req ON req.id = sess.requester_id
             JOIN users rec ON rec.id = sess.recipient_id
             WHERE sess.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Scoped to a participant -- a Member requesting another pair's
    // session-detail URL by guessing an id gets null here, before any
    // chat/notes content is ever fetched (FEATURES.md §3 "Chat privacy is
    // structural").
    public static function findForParticipant(int $id, int $userId): ?array
    {
        $session = self::find($id);
        if (!$session) {
            return null;
        }
        if ((int)$session['requester_id'] !== $userId && (int)$session['recipient_id'] !== $userId) {
            return null;
        }
        return $session;
    }

    public static function listForUser(int $userId, ?string $status = null): array
    {
        $sql = 'SELECT sess.*, sk.name AS skill_name,
                       req.name AS requester_name, rec.name AS recipient_name
                FROM sessions sess
                JOIN skills sk ON sk.id = sess.skill_id
                JOIN users req ON req.id = sess.requester_id
                JOIN users rec ON rec.id = sess.recipient_id
                WHERE (sess.requester_id = ? OR sess.recipient_id = ?)';
        $params = [$userId, $userId];
        if ($status !== null) {
            $sql .= ' AND sess.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY sess.scheduled_start DESC';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function upcomingForUser(int $userId, int $limit = 5): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT sess.*, sk.name AS skill_name,
                    req.name AS requester_name, rec.name AS recipient_name
             FROM sessions sess
             JOIN skills sk ON sk.id = sess.skill_id
             JOIN users req ON req.id = sess.requester_id
             JOIN users rec ON rec.id = sess.recipient_id
             WHERE (sess.requester_id = ? OR sess.recipient_id = ?)
               AND sess.status = 'confirmed' AND sess.scheduled_start >= NOW()
             ORDER BY sess.scheduled_start ASC
             LIMIT " . (int)$limit
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    // Requests awaiting THIS member's response (they are the recipient).
    public static function pendingForRecipient(int $userId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT sess.*, sk.name AS skill_name, req.name AS requester_name,
                    req.status AS requester_status
             FROM sessions sess
             JOIN skills sk ON sk.id = sess.skill_id
             JOIN users req ON req.id = sess.requester_id
             WHERE sess.recipient_id = ? AND sess.status = 'requested'
             ORDER BY sess.scheduled_start ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function create(int $skillId, int $requesterId, int $recipientId, string $start, string $end, ?string $notes): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "INSERT INTO sessions (skill_id, requester_id, recipient_id, scheduled_start, scheduled_end, status, notes)
             VALUES (?, ?, ?, ?, ?, 'requested', ?)"
        );
        $stmt->execute([$skillId, $requesterId, $recipientId, $start, $end, $notes]);
        return (int)$pdo->lastInsertId();
    }

    // Does this member already have a CONFIRMED session overlapping the
    // given time range? Used by accept() before a session can move to
    // 'confirmed' -- FEATURES.md §3 "Session overlap is rejected
    // server-side, not just in the UI", checked for BOTH participants.
    // FOR UPDATE so a call made inside acceptTransactionally()'s locked
    // transaction always reads the latest committed rows rather than a
    // REPEATABLE-READ snapshot; outside a transaction (autocommit) it's a
    // no-op and behaves like a plain read.
    public static function hasConfirmedOverlap(int $userId, string $start, string $end, ?int $excludeSessionId = null): bool
    {
        $sql = "SELECT id FROM sessions
                WHERE status = 'confirmed'
                  AND (requester_id = ? OR recipient_id = ?)
                  AND scheduled_start < ? AND scheduled_end > ?";
        $params = [$userId, $userId, $end, $start];
        if ($excludeSessionId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeSessionId;
        }
        $sql .= ' LIMIT 1 FOR UPDATE';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    private static function setStatus(int $id, string $status): void
    {
        $stmt = Database::connect()->prepare('UPDATE sessions SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    // Concurrency-safe accept. The read-then-write version of this used to
    // call find() and hasConfirmedOverlap() as plain SELECTs with no lock,
    // then a bare UPDATE -- two near-simultaneous 'accept' requests (a
    // double-click, or two browser tabs) could both read "no confirmed
    // overlap yet" before either commit, and both sessions would be set to
    // 'confirmed', producing exactly the double-booked-overlapping-
    // confirmed-sessions outcome the overlap check exists to prevent.
    //
    // Mirrors skill-exchange's core/SessionEngine.php pattern: everything
    // happens inside one transaction, guarded by SELECT ... FOR UPDATE row
    // locks, so a concurrent accept touching either participant is forced
    // to wait until this one commits or rolls back. Both participants'
    // `users` rows are locked (not just the recipient's) so the mirror-image
    // scenario -- the same REQUESTER with two overlapping pending requests
    // from two different recipients, accepted at the same instant -- is
    // also serialized. The two locks are always taken in ascending user-id
    // order so two concurrent accepts sharing both participants can never
    // deadlock each other.
    private static function acceptTransactionally(int $sessionId, int $requesterId, int $recipientId): array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $lockIds = $requesterId === $recipientId
                ? [$requesterId]
                : [min($requesterId, $recipientId), max($requesterId, $recipientId)];
            // The lock query doubles as the account-status check: a Member
            // deactivated by the Admin after sending (or receiving) a
            // request must not be able to end up in a CONFIRMED session,
            // and since this read is inside the locked transaction it can't
            // race against a concurrent deactivation either.
            $lockStmt = $pdo->prepare('SELECT id, status FROM users WHERE id = ? FOR UPDATE');
            foreach ($lockIds as $lockId) {
                $lockStmt->execute([$lockId]);
                $participant = $lockStmt->fetch();
                if (!$participant || $participant['status'] !== 'active') {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'This session cannot be confirmed: one of the participants\' accounts is no longer active.'];
                }
            }

            // Re-fetch the session under its own row lock: another
            // transaction may have already accepted/declined/cancelled it
            // while this one was waiting on the user-row locks above.
            $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? FOR UPDATE');
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if (!$session || $session['status'] !== 'requested') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'This session is no longer awaiting a response.'];
            }

            // request() rejects a start time in the past, but a request left
            // unanswered until after its own proposed slot has gone by was
            // still acceptable -- confirming a session into the past, which
            // is then immediately "Mark Completed"-able and contributes
            // nothing meaningful to the overlap check. The window has to be
            // re-validated at accept time, not only at request time.
            if (strtotime($session['scheduled_start']) <= time()) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'The proposed time for this request has already passed. Decline it and agree on a new slot.'];
            }

            if (
                self::hasConfirmedOverlap($requesterId, $session['scheduled_start'], $session['scheduled_end'], $sessionId)
                || self::hasConfirmedOverlap($recipientId, $session['scheduled_start'], $session['scheduled_end'], $sessionId)
            ) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'This time slot overlaps with an existing confirmed session for one of the participants.'];
            }

            $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ?')->execute(['confirmed', $sessionId]);
            $pdo->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // The single entry point for every status change. $action is one of
    // accept/decline/cancel/complete. Returns ['ok' => bool, 'error' =>
    // string|null] -- an invalid transition (wrong actor, wrong current
    // status, or an unmet time constraint) is rejected here, not silently
    // applied, and never even reaches the database as an UPDATE.
    public static function transition(int $sessionId, int $actorId, string $action): array
    {
        $session = self::find($sessionId);
        if (!$session) {
            return ['ok' => false, 'error' => 'Session not found.'];
        }

        $requesterId = (int)$session['requester_id'];
        $recipientId = (int)$session['recipient_id'];

        if ($actorId !== $requesterId && $actorId !== $recipientId) {
            return ['ok' => false, 'error' => 'You are not a participant in this session.'];
        }

        $status = $session['status'];

        switch ($action) {
            case 'accept':
                if ($status !== 'requested') {
                    return ['ok' => false, 'error' => 'Only a requested session can be accepted.'];
                }
                if ($actorId !== $recipientId) {
                    return ['ok' => false, 'error' => 'Only the recipient can accept a session request.'];
                }
                return self::acceptTransactionally($sessionId, $requesterId, $recipientId);

            case 'decline':
                if ($status !== 'requested') {
                    return ['ok' => false, 'error' => 'Only a requested session can be declined.'];
                }
                if ($actorId !== $recipientId) {
                    return ['ok' => false, 'error' => 'Only the recipient can decline a session request.'];
                }
                self::setStatus($sessionId, 'declined');
                return ['ok' => true, 'error' => null];

            case 'cancel':
                if (!in_array($status, ['requested', 'confirmed'], true)) {
                    return ['ok' => false, 'error' => 'Only a requested or confirmed session can be cancelled.'];
                }
                self::setStatus($sessionId, 'cancelled');
                return ['ok' => true, 'error' => null];

            case 'complete':
                if ($status !== 'confirmed') {
                    return ['ok' => false, 'error' => 'Only a confirmed session can be marked completed.'];
                }
                if (strtotime($session['scheduled_end']) > time()) {
                    return ['ok' => false, 'error' => "This session's scheduled time hasn't passed yet."];
                }
                self::setStatus($sessionId, 'completed');
                return ['ok' => true, 'error' => null];

            default:
                return ['ok' => false, 'error' => 'Unknown action.'];
        }
    }

    // ---- Dependency guards ----

    // Live (requested or confirmed) sessions that depend on this member
    // still teaching a given skill, keyed by skill_id. A member removing a
    // skill from their TEACH list while someone has a pending request or a
    // confirmed booking for exactly that skill would strand the other
    // participant with a session nobody is listed as able to deliver --
    // same parent-with-dependent-children shape this suite guards
    // elsewhere, and unguarded here until now.
    public static function activeCountsByTeachSkill(int $userId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT skill_id, COUNT(*) AS c
             FROM sessions
             WHERE recipient_id = ? AND status IN ('requested', 'confirmed')
             GROUP BY skill_id"
        );
        $stmt->execute([$userId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['skill_id']] = (int)$row['c'];
        }
        return $out;
    }

    // Upcoming confirmed sessions a member is still committed to -- shown
    // to the Admin when deactivating them, so the other participants left
    // holding those bookings aren't silently invisible.
    public static function liveCountForUser(int $userId): int
    {
        $stmt = Database::connect()->prepare(
            "SELECT COUNT(*) c FROM sessions
             WHERE (requester_id = ? OR recipient_id = ?)
               AND status IN ('requested', 'confirmed')"
        );
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetch()['c'];
    }

    // ---- Admin oversight (counts/joins only -- never selects `notes`) ----

    public static function countByStatus(): array
    {
        $stmt = Database::connect()->query('SELECT status, COUNT(*) AS c FROM sessions GROUP BY status');
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['status']] = (int)$row['c'];
        }
        return $out;
    }

    public static function totalCount(): int
    {
        $stmt = Database::connect()->query('SELECT COUNT(*) c FROM sessions');
        return (int)$stmt->fetch()['c'];
    }
}
