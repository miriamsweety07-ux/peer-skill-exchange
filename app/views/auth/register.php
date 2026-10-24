<?php require __DIR__ . '/../partials/header.php'; ?>
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <h1>SkillSwap</h1>
            <p class="text-muted mb-1">Peer-to-Peer Skill Exchange</p>
            <p class="login-tagline">Create a Member account to start teaching and learning.</p>
        </div>

        <?php if ($msg = flash_get('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="/index.php?route=register">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" required autofocus maxlength="100">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required minlength="8">
                <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-burnt w-100">Create Account</button>
        </form>

        <p class="login-register-link">Already have an account? <a href="/index.php?route=login">Log in</a></p>
    </div>
</div>
<?php $hideFooter = true; require __DIR__ . '/../partials/footer.php'; ?>
