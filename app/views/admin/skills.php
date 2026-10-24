<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">Skills Taxonomy</h1>
    <p class="page-subtitle">The shared master list Members pick from when building their profile -- deactivate rather than delete once a skill has member history.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <h2 class="section-title">Add a Skill</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/index.php?route=admin/skills/store">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Skill Name</label>
                            <input type="text" name="name" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" maxlength="60" required
                                   list="categoryList" placeholder="e.g. Programming, Music, Language">
                            <datalist id="categoryList">
                                <?php foreach (Skill::categories() as $cat): ?>
                                    <option value="<?= e($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <button type="submit" class="btn btn-burnt w-100">Add Skill</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <h2 class="section-title">All Skills</h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php foreach ($skills as $s): ?>
                        <form method="POST" action="/index.php?route=admin/skills/update" id="edit-form-<?= (int)$s['id'] ?>" class="d-none">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        </form>
                    <?php endforeach; ?>
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($skills as $s): ?>
                            <tr>
                                <td>
                                    <input type="text" form="edit-form-<?= (int)$s['id'] ?>" name="name" maxlength="100"
                                           class="form-control form-control-sm rename-input" value="<?= e($s['name']) ?>">
                                </td>
                                <td>
                                    <input type="text" form="edit-form-<?= (int)$s['id'] ?>" name="category" maxlength="60"
                                           class="form-control form-control-sm rename-input" value="<?= e($s['category']) ?>">
                                </td>
                                <td>
                                    <span class="badge <?= $s['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="submit" form="edit-form-<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-teal">Save</button>
                                    <form method="POST" action="/index.php?route=admin/skills/toggle" class="d-inline">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-danger' : 'btn-outline-burnt' ?>">
                                            <?= $s['is_active'] ? 'Deactivate' : 'Re-activate' ?>
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
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
