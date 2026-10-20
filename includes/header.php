<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Shared Page Header and Role-Aware Navigation
 */

declare(strict_types=1);

// Ensure bootstrap is loaded
require_once __DIR__ . '/bootstrap.php';

$pageTitle = isset($pageTitle) ? e($pageTitle) . ' — UrbanEats' : 'UrbanEats — Cloud Kitchen Platform';
$currentUser = current_user();
$currentRole = current_user_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="<?= base_url('index.php') ?>" class="brand" aria-label="UrbanEats Home">
            <span class="brand-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
                </svg>
            </span>
            <span>Urban<span class="brand-highlight">Eats</span></span>
        </a>

        <button class="mobile-nav-toggle" id="mobileNavBtn" aria-label="Toggle navigation menu" aria-expanded="false">
            ☰
        </button>

        <div class="header-right">
            <nav>
                <ul class="nav-links" id="mainNav">
                    <li><a href="<?= base_url('index.php') ?>" class="nav-link">Home</a></li>

                    <?php if (is_logged_in()): ?>
                        <?php if ($currentRole === 'admin'): ?>
                            <li><a href="<?= base_url('admin/index.php') ?>" class="nav-link">Dashboard</a></li>
                            <li><a href="<?= base_url('admin/users.php') ?>" class="nav-link">User Management</a></li>
                            <li><a href="<?= base_url('admin/audit-log.php') ?>" class="nav-link">Audit Logs</a></li>
                        <?php endif; ?>

                        <li><a href="<?= base_url('auth/profile.php') ?>" class="nav-link">My Profile</a></li>
                        <li><a href="<?= base_url('auth/change-password.php') ?>" class="nav-link">Security</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="nav-actions">
                <?php if (is_logged_in() && $currentUser): ?>
                    <div class="user-badge-nav">
                        <span class="user-name"><?= e($currentUser['username']) ?></span>
                        <span class="badge badge-<?= e($currentRole) ?>"><?= e(str_replace('_', ' ', (string)$currentRole)) ?></span>
                    </div>

                    <!-- Secure POST-based Logout form with CSRF protection -->
                    <form action="<?= base_url('auth/logout.php') ?>" method="POST" style="display:inline; margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm" title="Sign out of your account">
                            Sign Out
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline btn-sm">Sign In</a>
                    <a href="<?= base_url('auth/register.php') ?>" class="btn btn-primary btn-sm">Create Account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="container">
        <?php
        // Render any pending flash messages
        $flashMessages = get_flash_messages();
        if (!empty($flashMessages)):
            foreach ($flashMessages as $flash):
                $alertClass = 'alert-' . e($flash['type']);
                $icon = match ($flash['type']) {
                    'success' => '✅',
                    'danger'  => '⚠️',
                    'warning' => '⚡',
                    default   => 'ℹ️',
                };
        ?>
            <div class="alert <?= $alertClass ?>" role="alert">
                <span><?= $icon ?></span>
                <div><?= e($flash['message']) ?></div>
            </div>
        <?php
            endforeach;
        endif;
        ?>
