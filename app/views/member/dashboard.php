<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">Welcome back, <?= e(Auth::name()) ?></h1>
    <p class="page-subtitle">Your upcoming sessions, pending requests, and a preview of your best matches.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Skills You Teach</div>
                <div class="stat-value"><?= (int)$teachCount ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Skills You Want</div>
                <div class="stat-value"><?= (int)$learnCount ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value"><?= count($pending) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Upcoming Sessions</div>
                <div class="stat-value"><?= count($upcoming) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <h2 class="section-title">Pending Requests Needing Your Response</h2>
            <?php if (!$pending): ?>
                <div class="card"><div class="card-body text-muted">Nothing waiting on you right now.</div></div>
            <?php else: ?>
                <?php foreach ($pending as $s): ?>
                    <div class="card mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e($s['requester_name']) ?></strong> wants a <strong><?= e($s['skill_name']) ?></strong> session
                                <?php if ($s['requester_status'] !== 'active'): ?>
                                    <span class="badge badge-locked ms-1">account deactivated</span>
                                <?php elseif (strtotime($s['scheduled_start']) <= time()): ?>
                                    <span class="badge badge-locked ms-1">slot passed</span>
                                <?php endif; ?>
                                <div class="text-muted small"><?= format_datetime($s['scheduled_start']) ?></div>
                            </div>
                            <a href="/index.php?route=member/sessions/detail&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-burnt">Respond</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2 class="section-title">Upcoming Confirmed Sessions</h2>
            <?php if (!$upcoming): ?>
                <div class="card"><div class="card-body text-muted">No confirmed sessions on the calendar yet.</div></div>
            <?php else: ?>
                <?php foreach ($upcoming as $s): ?>
                    <?php $other = (int)$s['requester_id'] === Auth::id() ? $s['recipient_name'] : $s['requester_name']; ?>
                    <div class="card mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e($s['skill_name']) ?></strong> with <strong><?= e($other) ?></strong>
                                <div class="text-muted small"><?= format_datetime($s['scheduled_start']) ?></div>
                            </div>
                            <a href="/index.php?route=member/sessions/detail&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-teal">View</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <h2 class="section-title">Quick Matches Preview</h2>
            <?php if (!$matches): ?>
                <div class="card"><div class="card-body text-muted">Add skills you want to learn on your profile to see matches here.</div></div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($matches as $m): ?>
                        <div class="col-md-6">
                            <div class="match-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong><?= e($m['name']) ?></strong>
                                    <?php if ($m['mutual']): ?><span class="badge mutual-badge">Mutual</span><?php endif; ?>
                                </div>
                                <div class="small text-muted mb-1">teaches</div>
                                <span class="skill-chip"><?= e($m['skill_name']) ?> &middot; <?= proficiency_label($m['proficiency_level']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="/index.php?route=member/directory&tab=matches" class="btn btn-outline-burnt mt-3">See All Matches</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
