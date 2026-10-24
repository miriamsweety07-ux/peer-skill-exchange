<?php

declare(strict_types=1);

// Let PHP's built-in dev server (`php -S`) serve real static files directly
// instead of routing every asset request through the front controller.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $assetPath = __DIR__ . $requestPath;
    if (!str_ends_with($requestPath, '.php') && is_file($assetPath)) {
        return false;
    }
}

date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Project-specific session cookie name. Cookies are scoped by host, NOT by
// port, so every project in this suite shipping the default `PHPSESSID`
// means logging into one `localhost:PORT` project silently overwrites the
// session of any other one open in the same browser.
session_name('SKILLSWAP_SESSID');

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Router.php';

require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Skill.php';
require_once __DIR__ . '/../app/models/MemberSkill.php';
require_once __DIR__ . '/../app/models/Session.php';
require_once __DIR__ . '/../app/models/Message.php';

require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/AdminController.php';
require_once __DIR__ . '/../app/controllers/MemberController.php';
require_once __DIR__ . '/../app/controllers/SessionController.php';

$router = new Router();

// ---- Auth ----
$router->get('login', fn() => (new AuthController())->showLogin());
$router->post('login', fn() => (new AuthController())->login());
$router->post('logout', fn() => (new AuthController())->logout());
$router->get('register', fn() => (new AuthController())->showRegister());
$router->post('register', fn() => (new AuthController())->register());

// ---- Super Admin ----
$router->get('admin/dashboard', fn() => (new AdminController())->dashboard());
$router->get('admin/members', fn() => (new AdminController())->members());
$router->post('admin/members/status', fn() => (new AdminController())->memberStatus());
$router->get('admin/skills', fn() => (new AdminController())->skills());
$router->post('admin/skills/store', fn() => (new AdminController())->skillStore());
$router->post('admin/skills/update', fn() => (new AdminController())->skillUpdate());
$router->post('admin/skills/toggle', fn() => (new AdminController())->skillToggle());

// ---- Member ----
$router->get('member/dashboard', fn() => (new MemberController())->dashboard());
$router->get('member/profile', fn() => (new MemberController())->profile());
$router->post('member/profile/bio', fn() => (new MemberController())->profileBio());
$router->post('member/skills/add', fn() => (new MemberController())->skillAdd());
$router->post('member/skills/remove', fn() => (new MemberController())->skillRemove());
$router->get('member/directory', fn() => (new MemberController())->directory());
$router->get('member/sessions', fn() => (new MemberController())->sessions());
$router->get('member/sessions/detail', fn() => (new SessionController())->detail());

// ---- Sessions (scheduling + chat, both reachable only by Members) ----
$router->post('sessions/request', fn() => (new SessionController())->request());
$router->post('sessions/accept', fn() => (new SessionController())->accept());
$router->post('sessions/decline', fn() => (new SessionController())->decline());
$router->post('sessions/cancel', fn() => (new SessionController())->cancel());
$router->post('sessions/complete', fn() => (new SessionController())->complete());
$router->get('sessions/messages/poll', fn() => (new SessionController())->messagesPoll());
$router->post('sessions/messages/send', fn() => (new SessionController())->messagesSend());

$route = $_GET['route'] ?? '';

if ($route === '') {
    if (Auth::check()) {
        redirect(Auth::dashboardRoute(Auth::role()));
    }
    redirect('login');
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $route);
