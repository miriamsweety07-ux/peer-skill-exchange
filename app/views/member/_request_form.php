<?php
// Included from directory.php for each teacher/match row. Expects $skill
// (id, name), $t (user_id, name) and $formId in scope.
?>
<div id="<?= e($formId) ?>" class="d-none border rounded p-3 mb-2 bg-light">
    <form method="POST" action="/index.php?route=sessions/request" class="row g-2">
        <?= Csrf::field() ?>
        <input type="hidden" name="return_to" value="member/directory">
        <input type="hidden" name="recipient_id" value="<?= (int)$t['user_id'] ?>">
        <input type="hidden" name="skill_id" value="<?= (int)$skill['id'] ?>">
        <div class="col-12 small text-muted">
            Requesting <strong><?= e($skill['name']) ?></strong> with <strong><?= e($t['name']) ?></strong>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small">Time</label>
            <input type="time" name="time" class="form-control form-control-sm" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small">Duration</label>
            <select name="duration" class="form-select form-select-sm">
                <?php foreach (SessionController::durations() as $d): ?>
                    <option value="<?= $d ?>" <?= $d === 60 ? 'selected' : '' ?>><?= $d ?> min</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-teal w-100">Send Request</button>
        </div>
        <div class="col-12">
            <input type="text" name="notes" class="form-control form-control-sm" maxlength="255" placeholder="Optional note (what you'd like to cover)">
        </div>
    </form>
</div>
