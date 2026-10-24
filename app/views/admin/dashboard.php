<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">Platform Dashboard</h1>
    <p class="page-subtitle">A read-only overview built entirely from member/session counts -- never from chat messages or session notes.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Active Members</div>
                <div class="stat-value"><?= (int)$activeMembers ?></div>
                <div class="text-muted small"><?= (int)$totalMembers ?> total accounts</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Total Sessions</div>
                <div class="stat-value"><?= (int)$totalSessions ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Confirmed / Upcoming</div>
                <div class="stat-value"><?= (int)($sessionCounts['confirmed'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= (int)($sessionCounts['completed'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <h2 class="section-title">Sessions by Status</h2>
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php foreach (Session::STATUSES as $status): ?>
                            <tr>
                                <td><span class="badge <?= status_badge_class($status) ?>"><?= status_label($status) ?></span></td>
                                <td class="text-end fw-semibold"><?= (int)($sessionCounts[$status] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="section-title">Most-Requested Skills</h2>
            <div class="card">
                <div class="card-body">
                    <?php if (!$topSkills): ?>
                        <p class="text-muted mb-0">No sessions have been requested yet.</p>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr><th>Skill</th><th>Category</th><th class="text-end">Sessions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topSkills as $skill): ?>
                                <tr>
                                    <td><?= e($skill['name']) ?></td>
                                    <td class="text-muted"><?= e($skill['category']) ?></td>
                                    <td class="text-end fw-semibold"><?= (int)$skill['session_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
