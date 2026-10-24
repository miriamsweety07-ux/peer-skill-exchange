<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $route): void
{
    header('Location: /index.php?route=' . $route);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function flash_get(string $type): ?string
{
    if (empty($_SESSION['flash'][$type])) {
        return null;
    }
    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $message;
}

function old(array $data, string $key, string $default = ''): string
{
    return e($data[$key] ?? $default);
}

function format_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts === false ? '-' : date('d M Y', $ts);
}

function format_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('d M Y, h:i A', $ts);
}

function format_time(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('h:i A', $ts);
}

// Human label for a session status value.
function status_label(string $status): string
{
    return match ($status) {
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'declined'  => 'Declined',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        default     => ucfirst($status),
    };
}

// Bootstrap badge color class per session status -- kept in one place so
// every view (dashboard, session list, session detail) renders identically.
function status_badge_class(string $status): string
{
    return match ($status) {
        'requested' => 'badge-status-requested',
        'confirmed' => 'badge-status-confirmed',
        'declined'  => 'badge-status-declined',
        'cancelled' => 'badge-status-cancelled',
        'completed' => 'badge-status-completed',
        default     => 'bg-secondary',
    };
}

function proficiency_label(string $level): string
{
    return match ($level) {
        'beginner'     => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced'     => 'Advanced',
        'expert'       => 'Expert',
        default        => ucfirst($level),
    };
}
