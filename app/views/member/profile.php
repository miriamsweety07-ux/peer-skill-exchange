<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<div class="container py-4">
    <h1 class="page-title">My Skill Profile</h1>
    <p class="page-subtitle">Maintain your bio and the two skill lists other Members see you by: what you can teach, and what you want to learn.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <h2 class="section-title">Bio</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/index.php?route=member/profile/bio">
                        <?= Csrf::field() ?>
                        <textarea name="bio" class="form-control mb-3" rows="4" maxlength="500"
                                  placeholder="Tell other members a bit about yourself"><?= e($user['bio'] ?? '') ?></textarea>
                        <button type="submit" class="btn btn-burnt w-100">Save Bio</button>
                    </form>
                </div>
            </div>

            <h2 class="section-title">Add a Skill</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/index.php?route=member/skills/add" id="skillAddForm">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Skill</label>
                            <select name="skill_id" class="form-select" required>
                                <option value="">Choose a skill&hellip;</option>
                                <?php foreach ($skills as $sk): ?>
                                    <option value="<?= (int)$sk['id'] ?>"><?= e($sk['category']) ?> &middot; <?= e($sk['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">I want to&hellip;</label>
                            <select name="type" class="form-select" id="skillTypeSelect" required>
                                <option value="teach">Teach it</option>
                                <option value="learn">Learn it</option>
                            </select>
                        </div>
                        <div class="mb-3" id="proficiencyField">
                            <label class="form-label">Your Proficiency</label>
                            <select name="proficiency_level" class="form-select">
                                <?php foreach ($levels as $lvl): ?>
                                    <option value="<?= e($lvl) ?>"><?= proficiency_label($lvl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-teal w-100">Add to Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <h2 class="section-title">Skills I Teach</h2>
            <?php if (!$teaching): ?>
                <div class="card"><div class="card-body text-muted">You haven't listed anything you can teach yet.</div></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table mb-0 align-middle">
                            <tbody>
                            <?php foreach ($teaching as $row): ?>
                                <tr>
                                    <td>
                                        <span class="skill-chip"><?= e($row['skill_name']) ?></span>
                                        <span class="text-muted small ms-1"><?= e($row['skill_category']) ?></span>
                                        <?php if (!$row['skill_active']): ?><span class="badge text-bg-secondary ms-1">deactivated</span><?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-proficiency"><?= proficiency_label($row['proficiency_level']) ?></span></td>
                                    <td class="text-end">
                                        <?php $live = $lockedTeachSkills[(int)$row['skill_id']] ?? 0; ?>
                                        <?php if ($live > 0): ?>
                                            <span class="badge badge-locked" title="Remove is locked while sessions depend on this skill">
                                                <?= $live ?> live session<?= $live === 1 ? '' : 's' ?>
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" action="/index.php?route=member/skills/remove" class="d-inline">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="section-title">Skills I Want to Learn</h2>
            <?php if (!$learning): ?>
                <div class="card"><div class="card-body text-muted">You haven't listed anything you want to learn yet.</div></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table mb-0 align-middle">
                            <tbody>
                            <?php foreach ($learning as $row): ?>
                                <tr>
                                    <td>
                                        <span class="skill-chip chip-teal"><?= e($row['skill_name']) ?></span>
                                        <span class="text-muted small ms-1"><?= e($row['skill_category']) ?></span>
                                        <?php if (!$row['skill_active']): ?><span class="badge text-bg-secondary ms-1">deactivated</span><?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="/index.php?route=member/skills/remove" class="d-inline">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.getElementById('skillTypeSelect').addEventListener('change', function () {
    document.getElementById('proficiencyField').classList.toggle('d-none', this.value !== 'teach');
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
