<?php require __DIR__ . '/../partials/header.php'; ?>
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <h1>SkillSwap</h1>
            <p class="text-muted mb-1">Peer-to-Peer Skill Exchange</p>
            <p class="login-tagline">Trade skills with fellow members. Teach what you know, learn what you don't.</p>
        </div>

        <?php if ($msg = flash_get('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash_get('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="/index.php?route=login">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-burnt w-100">Log In</button>
        </form>

        <p class="login-register-link">New here? <a href="/index.php?route=register">Create a Member account</a></p>

        <div class="demo-creds">
            <p class="fw-semibold mb-1">Demo credentials</p>
            <table class="table table-sm mb-0">
                <tbody>
                <tr><td>Super Admin</td><td><code>admin@skillswap.test</code></td><td><code>Admin@123</code></td></tr>
                <tr><td>Member (Rohan Verma)</td><td><code>member1@skillswap.test</code></td><td><code>Member@123</code></td></tr>
                <tr><td>Member (Sneha Iyer)</td><td><code>member2@skillswap.test</code></td><td><code>Member@123</code></td></tr>
                <tr><td>Member (deactivated)</td><td><code>member18@skillswap.test</code></td><td><code>Member@123</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $hideFooter = true; require __DIR__ . '/../partials/footer.php'; ?>
