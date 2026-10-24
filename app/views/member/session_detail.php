<?php
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/navbar.php';

$iAmRequester = (int)$session['requester_id'] === Auth::id();
$other = $iAmRequester ? $session['recipient_name'] : $session['requester_name'];
$isRecipient = !$iAmRequester;
$isPast = strtotime($session['scheduled_end']) <= time();
$slotGone = strtotime($session['scheduled_start']) <= time();
$otherStatus = $iAmRequester ? $session['recipient_status'] : $session['requester_status'];
$otherInactive = $otherStatus !== 'active';
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php?route=member/dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/index.php?route=member/sessions">My Sessions</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($session['skill_name']) ?> with <?= e($other) ?></li>
        </ol>
    </nav>
    <h1 class="page-title mt-2"><?= e($session['skill_name']) ?> with <?= e($other) ?></h1>
    <p class="page-subtitle">
        <?= format_datetime($session['scheduled_start']) ?> &ndash; <?= format_time($session['scheduled_end']) ?>
        &middot; <span class="badge <?= status_badge_class($session['status']) ?>"><?= status_label($session['status']) ?></span>
    </p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <?php if ($otherInactive): ?>
        <div class="alert alert-warning">
            <strong><?= e($other) ?>'s account has been deactivated</strong> by the platform admin.
            This session is kept on record, but it can't be confirmed and they won't see new messages.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <h2 class="section-title">Session Details</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Skill</dt><dd class="col-7"><?= e($session['skill_name']) ?></dd>
                        <dt class="col-5">Requested by</dt><dd class="col-7"><?= e($session['requester_name']) ?></dd>
                        <dt class="col-5">Recipient</dt><dd class="col-7"><?= e($session['recipient_name']) ?></dd>
                        <dt class="col-5">Start</dt><dd class="col-7"><?= format_datetime($session['scheduled_start']) ?></dd>
                        <dt class="col-5">End</dt><dd class="col-7"><?= format_datetime($session['scheduled_end']) ?></dd>
                        <?php if (!empty($session['notes'])): ?>
                            <dt class="col-5">Note</dt><dd class="col-7"><?= e($session['notes']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <h2 class="section-title">Actions</h2>
            <div class="card">
                <div class="card-body d-flex flex-wrap gap-2">
                    <?php if ($session['status'] === 'requested' && $isRecipient): ?>
                        <?php if ($otherInactive): ?>
                            <span class="text-muted align-self-center small">
                                <?= e($other) ?>'s account is no longer active, so this request can't be confirmed. You can still decline it.
                            </span>
                        <?php elseif ($slotGone): ?>
                            <span class="text-muted align-self-center small">
                                The proposed time has already passed, so this request can no longer be accepted. Decline it and agree on a new slot.
                            </span>
                        <?php else: ?>
                            <form method="POST" action="/index.php?route=sessions/accept">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                                <button type="submit" class="btn btn-teal">Accept</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="/index.php?route=sessions/decline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger">Decline</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($session['status'] === 'requested' && $iAmRequester): ?>
                        <form method="POST" action="/index.php?route=sessions/cancel">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger">Withdraw Request</button>
                        </form>
                        <span class="text-muted align-self-center small">Waiting on <?= e($other) ?> to respond.</span>
                    <?php endif; ?>

                    <?php if ($session['status'] === 'confirmed'): ?>
                        <form method="POST" action="/index.php?route=sessions/cancel">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger">Cancel Session</button>
                        </form>
                        <?php if ($isPast): ?>
                            <form method="POST" action="/index.php?route=sessions/complete">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                                <button type="submit" class="btn btn-teal">Mark Completed</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted align-self-center small">Can be marked completed once the scheduled time has passed.</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (in_array($session['status'], ['declined', 'cancelled', 'completed'], true)): ?>
                        <span class="text-muted small">This session is <?= strtolower(status_label($session['status'])) ?> -- no further actions available.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <h2 class="section-title">Private Chat</h2>
            <div class="card">
                <div class="card-body">
                    <div id="chatThread" class="chat-thread mb-3"
                         data-session-id="<?= (int)$session['id'] ?>"
                         data-last-id="<?= (int)$lastMessageId ?>"
                         data-my-id="<?= (int)Auth::id() ?>">
                        <?php foreach ($messages as $m): ?>
                            <?php $mine = (int)$m['sender_id'] === Auth::id(); ?>
                            <div class="chat-bubble <?= $mine ? 'chat-bubble-mine' : 'chat-bubble-theirs' ?>">
                                <div class="chat-meta"><?= $mine ? 'You' : e($m['sender_name']) ?> &middot; <?= format_datetime($m['created_at']) ?></div>
                                <div><?= e($m['body']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" action="/index.php?route=sessions/messages/send" id="chatForm" class="d-flex gap-2">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                        <textarea name="body" class="form-control" rows="2" maxlength="1000" placeholder="Type a message&hellip;" required></textarea>
                        <button type="submit" class="btn btn-burnt align-self-end">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
