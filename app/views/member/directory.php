<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">Skill Directory / Find a Match</h1>
    <p class="page-subtitle">Browse the skill taxonomy to see who teaches what, or jump straight to your live Matches -- other members whose taught skills overlap what you want to learn.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'browse' ? 'active' : '' ?>" href="/index.php?route=member/directory&tab=browse">Browse Skills</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'matches' ? 'active' : '' ?>" href="/index.php?route=member/directory&tab=matches">
                My Matches <span class="badge text-bg-secondary"><?= count($matches) ?></span>
            </a>
        </li>
    </ul>

    <?php if ($tab === 'browse'): ?>
        <form method="GET" action="/index.php" class="row g-2 mb-4">
            <input type="hidden" name="route" value="member/directory">
            <input type="hidden" name="tab" value="browse">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Search by skill name or category&hellip;" value="<?= e($q) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-burnt w-100">Search</button>
            </div>
        </form>

        <?php if (!$teachersBySkill): ?>
            <div class="card"><div class="card-body text-muted">No skills found with an active teacher yet.</div></div>
        <?php endif; ?>

        <?php foreach ($skills as $skill): ?>
            <?php $teachers = $teachersBySkill[(int)$skill['id']] ?? []; if (!$teachers) continue; ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?= e($skill['name']) ?></span>
                    <span class="badge text-bg-light text-muted"><?= e($skill['category']) ?></span>
                </div>
                <div class="card-body">
                    <?php foreach ($teachers as $t): ?>
                        <?php $formId = 'req-browse-' . (int)$skill['id'] . '-' . (int)$t['user_id']; ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong><?= e($t['name']) ?></strong>
                                <span class="badge badge-proficiency ms-1"><?= proficiency_label($t['proficiency_level']) ?></span>
                                <?php if (!empty($t['bio'])): ?><div class="text-muted small"><?= e($t['bio']) ?></div><?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-burnt" data-toggle-request="<?= e($formId) ?>">Request Session</button>
                        </div>
                        <?php require __DIR__ . '/_request_form.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php if (!$matches): ?>
            <div class="card"><div class="card-body text-muted">No matches yet -- add skills you want to learn on your profile, then come back here.</div></div>
        <?php endif; ?>
        <div class="row g-3">
            <?php foreach ($matches as $m): ?>
                <?php $formId = 'req-match-' . (int)$m['skill_id'] . '-' . (int)$m['user_id']; ?>
                <div class="col-md-6">
                    <div class="match-card">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong><?= e($m['name']) ?></strong>
                            <?php if ($m['mutual']): ?><span class="badge mutual-badge">Mutual Exchange</span><?php endif; ?>
                        </div>
                        <?php if (!empty($m['bio'])): ?><div class="text-muted small mb-2"><?= e($m['bio']) ?></div><?php endif; ?>
                        <div class="small text-muted mb-1">teaches</div>
                        <span class="skill-chip mb-2"><?= e($m['skill_name']) ?> &middot; <?= proficiency_label($m['proficiency_level']) ?></span>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-burnt" data-toggle-request="<?= e($formId) ?>">Request Session</button>
                        </div>
                        <?php
                        $skill = ['id' => $m['skill_id'], 'name' => $m['skill_name']];
                        $t = ['user_id' => $m['user_id'], 'name' => $m['name']];
                        require __DIR__ . '/_request_form.php';
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
