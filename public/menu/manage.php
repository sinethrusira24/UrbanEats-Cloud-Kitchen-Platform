<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu, Catalogue and Inventory
 *
 * Kitchen Staff Dish Management Dashboard
 *
 * Requirements:
 * - Protected by Component A RBAC: require_role(['kitchen_staff', 'admin'])
 * - Comprehensive catalogue table with thumbnails, pricing, inventory balances, and status
 * - Search, category, and active/inactive status filters
 * - Direct triggers to Add/Edit dishes and soft-delete/restore controls
 * - Flash message display and CSRF-protected mutation forms
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/stock.php';
require_once dirname(__DIR__, 2) . '/includes/component_b.php';

// Enforce Kitchen Staff or Administrator role
require_role(['kitchen_staff', 'admin']);

$pdo = get_db_connection();

// ── 1. Filters & Search ────────────────────────────────────────────────────
$searchQuery    = trim((string)($_GET['q'] ?? ''));
$categoryFilter = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT) ?: null;
$statusFilter   = trim((string)($_GET['status'] ?? 'all')); // 'all', 'active', 'inactive'

$whereClauses = ['1=1'];
$queryParams  = [];

if ($searchQuery !== '') {
    $whereClauses[] = '(d.name LIKE :search_name OR d.description LIKE :search_desc)';
    $queryParams[':search_name'] = '%' . $searchQuery . '%';
    $queryParams[':search_desc'] = '%' . $searchQuery . '%';
}

if ($categoryFilter !== null && $categoryFilter > 0) {
    $whereClauses[] = 'd.category_id = :category_id';
    $queryParams[':category_id'] = $categoryFilter;
}

if ($statusFilter === 'active') {
    $whereClauses[] = 'd.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = 'd.is_active = 0';
}

$whereSql = implode(' AND ', $whereClauses);

// ── 2. Summary KPI Metrics ─────────────────────────────────────────────────
$statsSql = "SELECT 
    COUNT(d.dish_id) AS total_dishes,
    SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS active_dishes,
    SUM(CASE WHEN d.is_active = 0 THEN 1 ELSE 0 END) AS inactive_dishes,
    SUM(CASE WHEN d.is_active = 1 AND COALESCE(i.qty_available, 0) <= COALESCE(i.reorder_level, 5) THEN 1 ELSE 0 END) AS low_stock_dishes
FROM dishes d
LEFT JOIN inventory i ON d.dish_id = i.dish_id";
$statsStmt = $pdo->query($statsSql);
$stats = $statsStmt->fetch() ?: [
    'total_dishes'     => 0,
    'active_dishes'    => 0,
    'inactive_dishes'  => 0,
    'low_stock_dishes' => 0,
];

// ── 3. Fetch Catalogue Records ─────────────────────────────────────────────
$sql = "SELECT d.dish_id, d.category_id, d.name, d.description, d.price, d.image_path, d.is_active,
               d.created_at, d.updated_at,
               c.name AS category_name,
               COALESCE(i.qty_available, 0) AS qty_available,
               COALESCE(i.reorder_level, 5) AS reorder_level
        FROM dishes d
        JOIN categories c ON d.category_id = c.category_id
        LEFT JOIN inventory i ON d.dish_id = i.dish_id
        WHERE {$whereSql}
        ORDER BY d.is_active DESC, d.dish_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$dishes = $stmt->fetchAll();

// ── 4. Fetch Categories for Filter Dropdown ────────────────────────────────
$categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY display_order ASC, name ASC')->fetchAll();

$pageTitle = 'Dish Catalogue Management';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="catalogue-manage-wrapper">

    <!-- Dashboard Header -->
    <div class="catalogue-header-row">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <span class="badge badge-kitchen_staff" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    Kitchen Operations
                </span>
            </div>
            <h1 class="catalogue-header-title">Dish Catalogue Management</h1>
            <p class="catalogue-header-desc">
                Manage active menu offerings, adjust culinary specifications, and monitor real-time kitchen portion balances.
            </p>
        </div>

        <div class="catalogue-header-actions">
            <a href="<?= base_url('menu/stock.php') ?>" class="btn btn-outline" title="View live portion inventory and record batch restocking">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                <span>Stock Dashboard</span>
            </a>
            <a href="<?= base_url('menu/categories.php') ?>" class="btn btn-outline" title="Organize and reorder dish categories">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <span>Categories</span>
            </a>
            <a href="<?= base_url('menu/dish-form.php') ?>" class="btn btn-primary" title="Create a new dish offering">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add New Dish</span>
            </a>
        </div>
    </div>

    <!-- KPI Summary Overview -->
    <div class="catalogue-kpi-grid">
        <div class="catalogue-kpi-card">
            <div>
                <div class="catalogue-kpi-value"><?= (int)$stats['total_dishes'] ?></div>
                <div class="catalogue-kpi-label">Total Catalogue</div>
            </div>
            <div class="catalogue-kpi-icon" style="background: #f1f5f9; color: #475569;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </div>
        </div>

        <div class="catalogue-kpi-card">
            <div>
                <div class="catalogue-kpi-value" style="color: var(--primary);"><?= (int)$stats['active_dishes'] ?></div>
                <div class="catalogue-kpi-label">Active On Menu</div>
            </div>
            <div class="catalogue-kpi-icon" style="background: #ecfdf5; color: #059669;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
        </div>

        <div class="catalogue-kpi-card" style="<?= (int)$stats['low_stock_dishes'] > 0 ? 'border-color: var(--warning-border); background: #fffbeb;' : '' ?>">
            <div>
                <div class="catalogue-kpi-value" style="color: <?= (int)$stats['low_stock_dishes'] > 0 ? '#b45309' : '#475569' ?>;">
                    <?= (int)$stats['low_stock_dishes'] ?>
                </div>
                <div class="catalogue-kpi-label">Low Stock Alerts</div>
            </div>
            <div class="catalogue-kpi-icon" style="background: <?= (int)$stats['low_stock_dishes'] > 0 ? '#fef3c7' : '#f1f5f9' ?>; color: <?= (int)$stats['low_stock_dishes'] > 0 ? '#d97706' : '#94a3b8' ?>;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
        </div>

        <div class="catalogue-kpi-card">
            <div>
                <div class="catalogue-kpi-value"><?= count($categories) ?></div>
                <div class="catalogue-kpi-label">Categories Configured</div>
            </div>
            <div class="catalogue-kpi-icon" style="background: #f0f9ff; color: #0284c7;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <form method="GET" action="<?= base_url('menu/manage.php') ?>" class="menu-filter-bar" id="manageFilterForm">
        <div class="filter-search-wrap">
            <span class="filter-search-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <input type="text"
                   name="q"
                   id="manageSearchInput"
                   value="<?= e($searchQuery) ?>"
                   placeholder="Search dishes by name or description..."
                   class="filter-search-input"
                   aria-label="Search dishes"
                   autocomplete="off">
            <?php if ($searchQuery !== ''): ?>
                <button type="button" class="filter-search-clear" id="manageSearchClear" title="Clear search query" aria-label="Clear search">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            <?php endif; ?>
        </div>

        <div class="filter-controls">
            <!-- Category Dropdown with Custom Chevron -->
            <div class="filter-select-wrap">
                <svg class="filter-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <select name="cat" class="filter-select" onchange="this.form.submit()" aria-label="Filter by category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['category_id'] ?>" <?= $categoryFilter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <svg class="filter-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>

            <!-- Status Dropdown with Custom Chevron -->
            <div class="filter-select-wrap">
                <svg class="filter-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <select name="status" class="filter-select" onchange="this.form.submit()" aria-label="Filter by status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Deactivated Only</option>
                </select>
                <svg class="filter-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>

            <button type="submit" class="btn-filter-submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                <span>Filter</span>
            </button>

            <?php if ($searchQuery !== '' || $categoryFilter !== null || $statusFilter !== 'all'): ?>
                <a href="<?= base_url('menu/manage.php') ?>" class="btn-filter-reset" title="Reset all applied filters">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Active Filter Chips -->
    <?php
    $hasActiveFilters = ($searchQuery !== '' || $categoryFilter !== null || $statusFilter !== 'all');
    ?>
    <?php if ($hasActiveFilters): ?>
        <div class="active-filter-chips">
            <span class="active-filter-label">Active Filters:</span>

            <?php if ($categoryFilter !== null): ?>
                <?php
                $activeCatName = 'Selected';
                foreach ($categories as $c) {
                    if ((int)$c['category_id'] === $categoryFilter) {
                        $activeCatName = $c['name'];
                        break;
                    }
                }
                $catRemoveUrl = base_url('menu/manage.php?' . http_build_query(array_filter([
                    'q'      => $searchQuery !== '' ? $searchQuery : null,
                    'status' => $statusFilter !== 'all' ? $statusFilter : null,
                ])));
                ?>
                <a href="<?= $catRemoveUrl ?>" class="filter-chip" title="Remove category filter">
                    <span>Category: <strong><?= e($activeCatName) ?></strong></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($statusFilter !== 'all'): ?>
                <?php
                $statusRemoveUrl = base_url('menu/manage.php?' . http_build_query(array_filter([
                    'cat' => $categoryFilter,
                    'q'   => $searchQuery !== '' ? $searchQuery : null,
                ])));
                ?>
                <a href="<?= $statusRemoveUrl ?>" class="filter-chip" title="Remove status filter">
                    <span>Status: <strong><?= $statusFilter === 'active' ? 'Active Only' : 'Deactivated Only' ?></strong></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($searchQuery !== ''): ?>
                <?php
                $searchRemoveUrl = base_url('menu/manage.php?' . http_build_query(array_filter([
                    'cat'    => $categoryFilter,
                    'status' => $statusFilter !== 'all' ? $statusFilter : null,
                ])));
                ?>
                <a href="<?= $searchRemoveUrl ?>" class="filter-chip" title="Remove search filter">
                    <span>Search: "<strong><?= e($searchQuery) ?></strong>"</span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <a href="<?= base_url('menu/manage.php') ?>" class="filter-clear-all">Clear all</a>
        </div>
    <?php endif; ?>

    <!-- Results Status Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; font-size: 0.875rem; color: var(--text-muted);">
        <div>
            Showing <strong><?= count($dishes) ?></strong> <?= count($dishes) === 1 ? 'dish' : 'dishes' ?>
            <?php if ($hasActiveFilters): ?>
                matching applied criteria
            <?php else: ?>
                in catalogue
            <?php endif; ?>
        </div>
        <div style="font-size: 0.8125rem; color: var(--text-dim);">
            Kitchen Staff Live Operations
        </div>
    </div>

    <!-- Catalogue Table Card (Full Width, Crisp Styling) -->
    <div class="data-table-card">
        <div class="data-table-toolbar">
            <div class="data-table-toolbar-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span>Catalogue Offerings</span>
                <span class="data-table-count-badge"><?= count($dishes) ?> Records</span>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--text-muted);">
                <span class="status-pill active" style="padding: 0.2rem 0.55rem; font-size: 0.7rem;">
                    <span class="status-dot"></span> Live Ready
                </span>
            </div>
        </div>

        <div class="table-responsive" style="border: none; border-radius: 0; box-shadow: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 76px; text-align: center;">Item</th>
                        <th>Dish Name & Description</th>
                        <th style="width: 140px;">Category</th>
                        <th style="width: 130px;">Price (LKR)</th>
                        <th style="width: 160px;">Live Stock</th>
                        <th style="width: 110px; text-align: center;">Status</th>
                        <th style="width: 190px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dishes)): ?>
                        <tr>
                            <td colspan="7" style="padding: 4.5rem 1.5rem; text-align: center;">
                                <div style="display: inline-flex; width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; align-items: center; justify-content: center; color: var(--text-dim); margin-bottom: 1rem;">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                                    No Dishes Found
                                </h3>
                                <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 420px; margin: 0 auto 1.5rem;">
                                    <?= $hasActiveFilters ? 'No dishes matched your current search and filter settings. Try adjusting the query or resetting filters.' : 'There are no dishes configured in the catalogue yet. Add your first dish to get started.' ?>
                                </p>
                                <?php if ($hasActiveFilters): ?>
                                    <a href="<?= base_url('menu/manage.php') ?>" class="btn btn-outline btn-sm">
                                        Clear All Filters
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_url('menu/dish-form.php') ?>" class="btn btn-primary btn-sm">
                                        + Create First Dish
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dishes as $d): ?>
                            <?php
                            $isActive = (int)$d['is_active'] === 1;
                            $qty = (int)$d['qty_available'];
                            $reorder = (int)$d['reorder_level'];
                            $thumb = !empty($d['image_path']) ? base_url($d['image_path']) : base_url('images/hero_dish.jpg');
                            ?>
                            <tr class="<?= !$isActive ? 'is-deactivated' : '' ?>">
                                <td style="text-align: center;">
                                    <a href="<?= base_url('menu/detail.php?id=' . (int)$d['dish_id']) ?>" tabindex="-1">
                                        <div class="dish-item-thumb" style="margin: 0 auto;">
                                            <img src="<?= e($thumb) ?>"
                                                 alt="<?= e($d['name']) ?>"
                                                 loading="lazy"
                                                 onerror="this.onerror=null; this.src='<?= base_url('images/hero_dish.jpg') ?>';">
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <div class="dish-item-meta">
                                        <div class="dish-item-name">
                                            <a href="<?= base_url('menu/detail.php?id=' . (int)$d['dish_id']) ?>">
                                                <?= e($d['name']) ?>
                                            </a>
                                        </div>
                                        <div class="dish-item-desc">
                                            <?= e($d['description'] ?? 'No culinary description provided.') ?>
                                        </div>
                                        <div class="dish-item-sku">
                                            ID: #D-<?= str_pad((string)$d['dish_id'], 3, '0', STR_PAD_LEFT) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size: 0.75rem; font-weight: 600;">
                                        <?= e($d['category_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dish-price-text">
                                        <?= format_lkr((float)$d['price']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 4px;">
                                        <?= render_stock_badge($qty, $reorder, true) ?>
                                    </div>
                                    <div class="stock-bar-wrap" style="max-width: 110px; height: 5px;">
                                        <?php
                                        $pct = min(100, (int)(($qty / max(1, $reorder * 4)) * 100));
                                        $clrClass = $qty <= 0 ? 'low' : ($qty <= $reorder ? 'medium' : 'high');
                                        ?>
                                        <div class="stock-bar-fill <?= $clrClass ?>" style="width: <?= max(0, $pct) ?>%;"></div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($isActive): ?>
                                        <span class="status-pill active">
                                            <span class="status-dot"></span>
                                            <span>Active</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill inactive">
                                            <span class="status-dot"></span>
                                            <span>Inactive</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="dish-action-group">
                                        <!-- Preview Public Page Link -->
                                        <a href="<?= base_url('menu/detail.php?id=' . (int)$d['dish_id']) ?>"
                                           class="btn-action-icon btn-action-view"
                                           title="Preview guest dish page"
                                           aria-label="Preview dish">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            <span>View</span>
                                        </a>

                                        <!-- Edit Dish Link -->
                                        <a href="<?= base_url('menu/dish-form.php?id=' . (int)$d['dish_id']) ?>"
                                           class="btn-action-icon btn-action-edit"
                                           title="Edit dish specifications, pricing & image"
                                           aria-label="Edit dish">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                            </svg>
                                            <span>Edit</span>
                                        </a>

                                        <?php if ($isActive): ?>
                                            <!-- Soft-Delete Trigger Form -->
                                            <form method="POST" action="<?= base_url('menu/dish-delete.php') ?>" style="display: inline; margin: 0;"
                                                  onsubmit="return confirm('Are you sure you want to deactivate \'<?= e(addslashes($d['name'])) ?>\'? It will be hidden from the public menu.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="dish_id" value="<?= (int)$d['dish_id'] ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn-action-icon btn-action-danger" title="Deactivate dish from menu" aria-label="Deactivate dish">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                    <span>Deactivate</span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Restore Trigger Form -->
                                            <form method="POST" action="<?= base_url('menu/dish-delete.php') ?>" style="display: inline; margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="dish_id" value="<?= (int)$d['dish_id'] ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="btn-action-icon btn-action-success" title="Restore dish to public menu" aria-label="Restore dish">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="1 4 1 10 7 10"></polyline>
                                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                                    </svg>
                                                    <span>Restore</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search clear button helper
    const searchInput = document.getElementById('manageSearchInput');
    const clearBtn = document.getElementById('manageSearchClear');
    const filterForm = document.getElementById('manageFilterForm');

    if (clearBtn && searchInput && filterForm) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterForm.submit();
        });
    }
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
