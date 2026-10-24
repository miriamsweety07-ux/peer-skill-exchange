<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">My Sessions</h1>
    <p class="page-subtitle">Every session you've requested or been requested for, filterable by status.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <ul class="nav nav-pills mb-3 flex-wrap">
        <li class="nav-item mb-1">
            <a class="nav-link <?= $status === null ? 'active' : '' ?>" href="/index.php?route=member/sessions">All</a>
        </li>
        <?php foreach (Session::STATUSES as $st): ?>
            <li class="nav-item mb-1">
                <a class="nav-link <?= $status === $st ? 'active' : '' ?>" href="/index.php?route=member/sessions&status=<?= e($st) ?>">
                    <?= status_label($st) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$sessions): ?>
        <div class="card"><div class="card-body text-muted">No sessions in this view yet.</div></div>
    <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Skill</th>
                        <th>With</th>
                        <th>Scheduled</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <?php
                        $iAmRequester = (int)$s['requester_id'] === Auth::id();
                        $other = $iAmRequester ? $s['recipient_name'] : $s['requester_name'];
                        $role = $iAmRequester ? 'You requested' : 'Requested you';
                        ?>
                        <tr>
                            <td><?= e($s['skill_name']) ?></td>
                            <td><?= e($other) ?> <span class="text-muted small">(<?= e($role) ?>)</span></td>
                            <td><?= format_datetime($s['scheduled_start']) ?></td>
                            <td><span class="badge <?= status_badge_class($s['status']) ?>"><?= status_label($s['status']) ?></span></td>
                            <td class="text-end">
                                <a href="/index.php?route=member/sessions/detail&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-burnt">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
