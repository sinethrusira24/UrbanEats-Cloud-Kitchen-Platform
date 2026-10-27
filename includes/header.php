<?php
// Shared page header and role-aware navigation.

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
$cartCount = 0;
if (is_logged_in() && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = (int)array_sum($_SESSION['cart']);
}
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
                        <?php if ($currentRole === 'customer'): ?>
                            <li><a href="<?= base_url('orders/history.php') ?>" class="nav-link <?= str_contains($currentScript, 'orders/history.php') ? 'active' : '' ?>">My Orders</a></li>
                        <?php endif; ?>

                        <?php if ($currentRole === 'kitchen_staff'): ?>
                            <li><a href="<?= base_url('orders/kitchen.php') ?>" class="nav-link <?= str_contains($currentScript, 'orders/kitchen.php') ? 'active' : '' ?>">Kitchen Queue</a></li>
                            <li><a href="<?= base_url('menu/manage.php') ?>" class="nav-link <?= str_contains($currentScript, 'menu/manage.php') ? 'active' : '' ?>">Dishes</a></li>
                            <li><a href="<?= base_url('menu/stock.php') ?>" class="nav-link <?= str_contains($currentScript, 'menu/stock.php') ? 'active' : '' ?>">Stock</a></li>
                            <li><a href="<?= base_url('menu/categories.php') ?>" class="nav-link <?= str_contains($currentScript, 'menu/categories.php') ? 'active' : '' ?>">Categories</a></li>
                        <?php endif; ?>

                        <?php if ($currentRole === 'admin'): ?>
                            <!-- Admin Structured Navigation Dropdown -->
                            <li class="nav-item-dropdown">
                                <a href="<?= base_url('admin/index.php') ?>" class="nav-link nav-dropdown-toggle <?= ($isAdmin || $isKitchen) ? 'active' : '' ?>">
                                    <span>Administration</span>
                                    <span class="nav-caret">▾</span>
                                </a>
                                <ul class="nav-dropdown-menu">
                                    <li><a href="<?= base_url('admin/index.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/index.php') ? 'active' : '' ?>">Dashboard Overview</a></li>
                                    <li><a href="<?= base_url('admin/users.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/users.php') ? 'active' : '' ?>">User Management</a></li>
                                    <li><a href="<?= base_url('admin/audit-log.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/audit-log.php') ? 'active' : '' ?>">Security Audit Logs</a></li>
                                    <li><a href="<?= base_url('admin/reports.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'admin/reports.php') ? 'active' : '' ?>">Sales Reports</a></li>
                                    <li class="dropdown-divider-nav"></li>
                                    <li><a href="<?= base_url('orders/kitchen.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'orders/kitchen.php') ? 'active' : '' ?>">Kitchen Order Queue</a></li>
                                    <li><a href="<?= base_url('menu/manage.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'menu/manage.php') ? 'active' : '' ?>">Menu Dishes</a></li>
                                    <li><a href="<?= base_url('menu/stock.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'menu/stock.php') ? 'active' : '' ?>">Inventory & Stock</a></li>
                                    <li><a href="<?= base_url('menu/categories.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'menu/categories.php') ? 'active' : '' ?>">Categories</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="mobile-only-link"><a href="<?= base_url('orders/cart.php') ?>" class="nav-link <?= str_contains($currentScript, 'orders/cart.php') ? 'active' : '' ?>">My Cart (<?= $cartCount ?>)</a></li>
                        <li class="mobile-only-link"><a href="<?= base_url('orders/history.php') ?>" class="nav-link <?= str_contains($currentScript, 'orders/history.php') ? 'active' : '' ?>">My Orders</a></li>
                        <li class="mobile-only-link"><a href="<?= base_url('auth/profile.php') ?>" class="nav-link <?= str_contains($currentScript, 'auth/profile.php') ? 'active' : '' ?>">My Profile</a></li>
                        <li class="mobile-only-link"><a href="<?= base_url('auth/change-password.php') ?>" class="nav-link <?= str_contains($currentScript, 'auth/change-password.php') ? 'active' : '' ?>">Security</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="nav-actions">
                <?php if (is_logged_in() && $currentUser): ?>
                    <!-- Shopping Cart Button with Dynamic Badge -->
                    <a href="<?= base_url('orders/cart.php') ?>" class="nav-cart-btn" aria-label="Shopping Cart (<?= $cartCount ?> items)" title="View Shopping Cart">
                        <svg class="cart-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <?php if ($cartCount > 0): ?>
                            <span class="nav-cart-badge" id="navCartBadge"><?= $cartCount > 99 ? '99+' : $cartCount ?></span>
                        <?php endif; ?>
                    </a>

                    <div class="user-profile-menu">
                        <button type="button" class="user-badge-nav user-dropdown-btn" id="userMenuBtn" aria-haspopup="true" aria-expanded="false" title="Account settings">
                            <span class="user-name"><?= e(!empty($currentUser['full_name']) ? $currentUser['full_name'] : $currentUser['username']) ?></span>
                            <span class="dropdown-caret">▾</span>
                        </button>
                        <div class="user-dropdown-dropdown" id="userDropdownContent">
                            <div class="user-dropdown-header">
                                <span class="user-dropdown-fullname"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></span>
                                <span class="user-dropdown-email"><?= e($currentUser['email'] ?? '') ?></span>
                            </div>
                            <div class="dropdown-divider-nav"></div>
                            <a href="<?= base_url('orders/history.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'orders/history.php') ? 'active' : '' ?>">
                                My Orders
                            </a>
                            <a href="<?= base_url('auth/profile.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'auth/profile.php') ? 'active' : '' ?>">
                                My Profile
                            </a>
                            <a href="<?= base_url('auth/change-password.php') ?>" class="dropdown-link <?= str_contains($currentScript, 'auth/change-password.php') ? 'active' : '' ?>">
                                Security & Password
                            </a>
                            <div class="dropdown-divider-nav"></div>
                            <form action="<?= base_url('auth/logout.php') ?>" method="POST" class="user-dropdown-logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-link dropdown-link-danger">
                                    Sign Out
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
