<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Administrator Navigation Bar
 *
 * Dedicated sub-navigation bar providing seamless 1-click access
 * across all administrative modules and kitchen operations.
 */

declare(strict_types=1);

if (!function_exists('is_logged_in') || !is_logged_in() || current_user_role() !== 'admin') {
    return;
}

$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

$navTabs = [
    [
        'label' => 'Dashboard',
        'icon'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
        'url'   => base_url('admin/index.php'),
        'match' => 'admin/index.php',
    ],
    [
        'label' => 'User Accounts',
        'icon'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'url'   => base_url('admin/users.php'),
        'match' => 'admin/users.php',
    ],
    [
        'label' => 'Audit Log',
        'icon'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'url'   => base_url('admin/audit-log.php'),
        'match' => 'admin/audit-log.php',
    ],
    [
        'label' => 'Menu Dishes',
        'icon'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
        'url'   => base_url('menu/manage.php'),
        'match' => 'menu/manage.php',
    ],
    [
        'label' => 'Stock Control',
        'icon'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'url'   => base_url('menu/stock.php'),
        'match' => 'menu/stock.php',
    ],
    [
        'label' => 'Categories',
        'icon'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        'url'   => base_url('menu/categories.php'),
        'match' => 'menu/categories.php',
    ],
];
?>
<div class="admin-navbar-wrapper">
    <nav class="admin-navbar" aria-label="Administrator Console Navigation">
        <div class="admin-navbar-inner">
            <div class="admin-nav-header">
                <div class="admin-pill">
                    <span class="admin-pill-dot"></span>
                    <span>Admin Console</span>
                </div>
                <span class="admin-nav-divider"></span>
            </div>

            <div class="admin-nav-items">
                <?php foreach ($navTabs as $tab): ?>
                    <?php $isActive = str_contains($currentScript, $tab['match']); ?>
                    <a href="<?= $tab['url'] ?>"
                       class="admin-nav-item <?= $isActive ? 'active' : '' ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <span class="admin-nav-icon"><?= $tab['icon'] ?></span>
                        <span class="admin-nav-text"><?= e($tab['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="admin-nav-meta">
                <a href="<?= base_url('index.php') ?>" class="admin-nav-view-site" target="_blank" title="Preview public customer storefront">
                    <span>Live Store</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
            </div>
        </div>
    </nav>
</div>
