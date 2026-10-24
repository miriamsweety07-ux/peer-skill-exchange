<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">Manage Members</h1>
    <p class="page-subtitle">Activate or deactivate Member accounts. Deactivated members can no longer log in, but their skill profile, matches and session history stay intact.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= e($m['name']) ?></td>
                        <td><?= e($m['email']) ?></td>
                        <td>
                            <span class="badge <?= $m['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e(ucfirst($m['status'])) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= format_date($m['created_at']) ?></td>
                        <td class="text-end">
                            <form method="POST" action="/index.php?route=admin/members/status" class="d-inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="status" value="<?= $m['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="btn btn-sm <?= $m['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-teal' ?>">
                                    <?= $m['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
