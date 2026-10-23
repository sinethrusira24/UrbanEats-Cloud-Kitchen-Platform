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
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$isHome = str_ends_with($currentScript, 'index.php') && !str_contains($currentScript, '/admin/') && !str_contains($currentScript, '/menu/');
$isMenu = str_contains($currentScript, '/menu/index.php') || str_contains($currentScript, '/menu/detail.php');
$isAdmin = str_contains($currentScript, '/admin/');
$isKitchen = str_contains($currentScript, '/menu/manage.php') || str_contains($currentScript, '/menu/stock.php') || str_contains($currentScript, '/menu/categories.php') || str_contains($currentScript, '/menu/dish-form.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('images/logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="<?= base_url('index.php') ?>" class="brand" aria-label="UrbanEats Home">
            <img src="<?= base_url('images/logo.png') ?>" alt="UrbanEats Logo" class="brand-logo-img">
            <span>Urban<span class="brand-highlight">Eats</span></span>
        </a>

        <button class="mobile-nav-toggle" id="mobileNavBtn" aria-label="Toggle navigation menu" aria-expanded="false">
            ☰
        </button>

        <div class="header-right">
            <nav>
                <ul class="nav-links" id="mainNav">
                    <li><a href="<?= base_url('index.php') ?>" class="nav-link <?= $isHome ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= base_url('menu/index.php') ?>" class="nav-link <?= $isMenu ? 'active' : '' ?>">Menu</a></li>

                    <?php if (is_logged_in()): ?>
                        <?php if ($currentRole === 'kitchen_staff'): ?>
                            <li><a href="<?= base_url('menu/manage.php') ?>" class="nav-link <?= str_contains($currentScript, 'menu/manage.php') ? 'active' : '' ?>">Dishes</a></li>
                            <li><a href="<?= base_url('menu/stock.php') ?>" class="nav-link <?= str_contains($currentScript, 'menu/stock.php') ? 'active' : '' ?>">Stock</a></li>
                            <li><a href="<?= base_url('menu/categories.php') ?>" class="nav-link <?= str_contains($currentScript, 'menu/categories.php') ? 'active' : '' ?>">Categories</a></li>
                        <?php endif; ?>

                        <?php if ($currentRole === 'admin'): ?>
                            <!-- Admin Structured Navigation Dropdown -->
                            <li class="nav-item-dropdown">
                                <a href="<?= base_url('admin/index.php') ?>" class="nav-link nav-dropdown-toggle <?= ($isAdmin || $isKitchen) ? 'active' : '' ?>">
                                    <span>🛡️ Administration</span>
                                    <span class="nav-caret">▾</span>
                                </a>
                                <ul class="nav-dropdown-menu">
                                    <li><a href="<?= base_url('admin/index.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/index.php') ? 'active' : '' ?>">📊 Dashboard Overview</a></li>
                                    <li><a href="<?= base_url('admin/users.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/users.php') ? 'active' : '' ?>">👥 User Management</a></li>
                                    <li><a href="<?= base_url('admin/audit-log.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/audit-log.php') ? 'active' : '' ?>">📜 Security Audit Logs</a></li>
                                    <li class="dropdown-divider-nav"></li>
                                    <li><a href="<?= base_url('menu/manage.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'menu/manage.php') ? 'active' : '' ?>">🍽️ Menu Dishes</a></li>
                                    <li><a href="<?= base_url('menu/stock.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'menu/stock.php') ? 'active' : '' ?>">📦 Inventory & Stock</a></li>
                                    <li><a href="<?= base_url('menu/categories.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'menu/categories.php') ? 'active' : '' ?>">🏷️ Categories</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="mobile-only-link"><a href="<?= base_url('auth/profile.php') ?>" class="nav-link <?= str_contains($currentScript, 'auth/profile.php') ? 'active' : '' ?>">My Profile</a></li>
                        <li class="mobile-only-link"><a href="<?= base_url('auth/change-password.php') ?>" class="nav-link <?= str_contains($currentScript, 'auth/change-password.php') ? 'active' : '' ?>">Security</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="nav-actions">
                <?php if (is_logged_in() && $currentUser): ?>
                    <div class="user-profile-menu">
                        <button type="button" class="user-badge-nav user-dropdown-btn" id="userMenuBtn" aria-haspopup="true" aria-expanded="false" title="Account settings">
                            <span class="user-name"><?= e($currentUser['username']) ?></span>
                            <span class="badge badge-<?= e($currentRole) ?>"><?= e(str_replace('_', ' ', (string)$currentRole)) ?></span>
                            <span class="dropdown-caret">▾</span>
                        </button>
                        <div class="user-dropdown-dropdown" id="userDropdownContent">
                            <div class="user-dropdown-header">
                                <span class="user-dropdown-fullname"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></span>
                                <span class="user-dropdown-email"><?= e($currentUser['email'] ?? '') ?></span>
                            </div>
                            <div class="dropdown-divider-nav"></div>
                            <a href="<?= base_url('auth/profile.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'auth/profile.php') ? 'active' : '' ?>">
                                👤 My Profile
                            </a>
                            <a href="<?= base_url('auth/change-password.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'auth/change-password.php') ? 'active' : '' ?>">
                                🔐 Security & Password
                            </a>
                            <div class="dropdown-divider-nav"></div>
                            <form action="<?= base_url('auth/logout.php') ?>" method="POST" class="user-dropdown-logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-link dropdown-link-danger">
                                    🚪 Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
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
