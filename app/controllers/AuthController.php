<?php

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(Auth::dashboardRoute(Auth::role()));
        }
        $pageTitle = 'Log In';
        require __DIR__ . '/../views/auth/login.php';
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            redirect(Auth::dashboardRoute(Auth::role()));
        }
        $pageTitle = 'Create Account';
        require __DIR__ . '/../views/auth/register.php';
    }

    // Self-registration always creates a 'member' account -- there is no
    // form field for role, so a registrant can never provision themselves
    // as Super Admin. Matches this suite's CampusConnect precedent (project
    // #11): self-registered accounts are active immediately, no email
    // verification step.
    public function register(): void
    {
        Csrf::requireValid();

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        $v = (new Validator())
            ->required($_POST, 'name', 'Name')
            ->maxLength($_POST, 'name', 100, 'Name')
            ->required($_POST, 'email', 'Email')
            ->email($_POST, 'email', 'Email')
            ->required($_POST, 'password', 'Password')
            ->minLength($_POST, 'password', 8, 'Password');

        if ($v->fails()) {
            flash_set('error', implode(' ', $v->errors()));
            redirect('register');
        }

        if ($password !== $confirm) {
            flash_set('error', 'Password and confirmation do not match.');
            redirect('register');
        }

        if (User::emailExists($email)) {
            flash_set('error', 'An account with that email already exists.');
            redirect('register');
        }

        User::create($name, $email, $password, 'member');
        flash_set('success', 'Account created. Log in with your new credentials.');
        redirect('login');
    }

    public function login(): void
    {
        Csrf::requireValid();

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash_set('error', 'Enter both email and password.');
            redirect('login');
        }

        if (!Auth::attempt($email, $password)) {
            flash_set('error', 'Invalid credentials, or this account has been deactivated.');
            redirect('login');
        }

        redirect(Auth::dashboardRoute(Auth::role()));
    }

    public function logout(): void
    {
        Csrf::requireValid();
        Auth::logout();
        redirect('login');
    }
}
