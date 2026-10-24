<?php

require_once __DIR__ . '/../models/Session.php';
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/MemberSkill.php';
require_once __DIR__ . '/../models/Message.php';

// Session scheduling + the private per-session chat. Every action here
// re-derives the return route from where the request was submitted, and
// every read/write against one session is scoped through
// Session::findForParticipant() first -- a Member guessing another pair's
// session id gets a 404, never a peek at the schedule or the chat.
class SessionController
{
    private const DURATIONS = [30, 60, 90, 120];

    public function request(): void
    {
        Auth::requireRole('member');
        Csrf::requireValid();

        $userId = Auth::id();
        $recipientId = (int)($_POST['recipient_id'] ?? 0);
        $skillId = (int)($_POST['skill_id'] ?? 0);
        $date = (string)($_POST['date'] ?? '');
        $time = (string)($_POST['time'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $returnTo = $this->returnRoute();

        if ($recipientId === $userId) {
            flash_set('error', 'You cannot request a session with yourself.');
            redirect($returnTo);
        }

        $recipient = User::findActive($recipientId);
        if (!$recipient) {
            flash_set('error', 'That member is not available.');
            redirect($returnTo);
        }

        $skill = Skill::find($skillId);
        if (!$skill || !$skill['is_active']) {
            flash_set('error', 'Choose a valid, active skill.');
            redirect($returnTo);
        }

        if (!MemberSkill::exists($recipientId, $skillId, 'teach')) {
            flash_set('error', 'That member does not teach this skill.');
            redirect($returnTo);
        }

        if (!in_array($duration, self::DURATIONS, true)) {
            flash_set('error', 'Choose a valid session duration.');
            redirect($returnTo);
        }

        $start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if (!$start || $start->format('Y-m-d') !== $date) {
            flash_set('error', 'Choose a valid date and time.');
            redirect($returnTo);
        }
        if ($start->getTimestamp() <= time()) {
            flash_set('error', 'The proposed time must be in the future.');
            redirect($returnTo);
        }
        if (strlen($notes) > 255) {
            flash_set('error', 'Note must be at most 255 characters.');
            redirect($returnTo);
        }

        $end = (clone $start)->modify("+{$duration} minutes");

        Session::create(
            $skillId,
            $userId,
            $recipientId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $notes !== '' ? $notes : null
        );

        flash_set('success', 'Session request sent to ' . $recipient['name'] . '.');
        redirect($returnTo);
    }

    public function accept(): void
    {
        $this->act('accept', 'Session confirmed.');
    }

    public function decline(): void
    {
        $this->act('decline', 'Session declined.');
    }

    public function cancel(): void
    {
        $this->act('cancel', 'Session cancelled.');
    }

    public function complete(): void
    {
        $this->act('complete', 'Session marked completed.');
    }

    private function act(string $action, string $successMessage): void
    {
        Auth::requireRole('member');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $result = Session::transition($id, Auth::id(), $action);

        if ($result['ok']) {
            flash_set('success', $successMessage);
        } else {
            flash_set('error', $result['error']);
        }

        redirect('member/sessions/detail&id=' . $id);
    }

    public function detail(): void
    {
        Auth::requireRole('member');

        $id = (int)($_GET['id'] ?? 0);
        $session = Session::findForParticipant($id, Auth::id());
        if (!$session) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        $pageTitle = 'Session Detail';
        $messages = Message::forSession($id);
        $lastMessageId = $messages ? (int)end($messages)['id'] : 0;

        require __DIR__ . '/../views/member/session_detail.php';
    }

    // AJAX poll -- returns JSON of any messages newer than after_id. Scoped
    // through findForParticipant() exactly like detail(), so a
    // non-participant polling a guessed session id gets an empty 404 JSON
    // body, never message rows.
    public function messagesPoll(): void
    {
        Auth::requireRole('member');
        header('Content-Type: application/json');

        $sessionId = (int)($_GET['session_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);

        $session = Session::findForParticipant($sessionId, Auth::id());
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        $messages = Message::forSessionSince($sessionId, $afterId);
        echo json_encode(['messages' => array_map(function ($m) {
            return [
                'id'          => (int)$m['id'],
                'sender_id'   => (int)$m['sender_id'],
                'sender_name' => $m['sender_name'],
                'body'        => $m['body'],
                'created_at'  => format_datetime($m['created_at']),
            ];
        }, $messages)]);
    }

    public function messagesSend(): void
    {
        Auth::requireRole('member');
        Csrf::requireValid();

        $sessionId = (int)($_POST['session_id'] ?? 0);
        $body = trim((string)($_POST['body'] ?? ''));

        $session = Session::findForParticipant($sessionId, Auth::id());
        if (!$session) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }

        if ($body === '') {
            flash_set('error', 'Message cannot be empty.');
            redirect('member/sessions/detail&id=' . $sessionId);
        }
        if (strlen($body) > 1000) {
            flash_set('error', 'Message is too long (max 1000 characters).');
            redirect('member/sessions/detail&id=' . $sessionId);
        }

        Message::send($sessionId, Auth::id(), $body);
        redirect('member/sessions/detail&id=' . $sessionId);
    }

    public static function durations(): array
    {
        return self::DURATIONS;
    }

    private function returnRoute(): string
    {
        $to = $_POST['return_to'] ?? '';
        $allowed = ['member/directory', 'member/dashboard'];
        return in_array($to, $allowed, true) ? $to : 'member/directory';
    }
}
