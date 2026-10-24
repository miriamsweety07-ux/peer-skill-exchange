<?php
$role = Auth::role();
$navItems = [];
if ($role === 'super_admin') {
    $navItems = [
        'admin/dashboard' => 'Dashboard',
        'admin/members'   => 'Members',
        'admin/skills'    => 'Skills Taxonomy',
    ];
} elseif ($role === 'member') {
    $navItems = [
        'member/dashboard' => 'Dashboard',
        'member/profile'   => 'My Skill Profile',
        'member/directory' => 'Find a Match',
        'member/sessions'  => 'My Sessions',
    ];
}
$currentRoute = $_GET['route'] ?? '';
?>
<nav class="navbar navbar-expand-lg skillswap-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="/index.php?route=<?= Auth::check() ? e(Auth::dashboardRoute($role)) : 'login' ?>">
            SkillSwap
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <?php foreach ($navItems as $route => $label): ?>
                    <li class="nav-item">
                        <a href="/index.php?route=<?= e($route) ?>"
                           class="nav-link <?= $currentRoute === $route ? 'active' : '' ?>">
                            <?= $label ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center navbar-user">
                <span class="me-2 user-name"><?= e(Auth::name()) ?></span>
                <span class="badge user-role-badge me-2"><?= $role === 'super_admin' ? 'Super Admin' : 'Member' ?></span>
                <form method="POST" action="/index.php?route=logout" class="d-inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>
