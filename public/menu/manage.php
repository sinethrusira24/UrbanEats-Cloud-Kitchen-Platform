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

// ── 2. Fetch Catalogue Records ─────────────────────────────────────────────
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

// ── 3. Fetch Categories for Filter Dropdown ────────────────────────────────
$categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY display_order ASC, name ASC')->fetchAll();

$pageTitle = 'Dish Management';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container" style="padding: 1.5rem 0 3rem;">

    <!-- Dashboard Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-kitchen_staff" style="font-size: 0.75rem; text-transform: uppercase;">Kitchen Operations</span>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0.25rem 0 0; color: var(--text-main);">Dish Catalogue Management</h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.9375rem;">Manage active menu offerings, update culinary details, and control item visibility.</p>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= base_url('menu/stock.php') ?>" class="btn btn-outline">
                📊 Stock Dashboard
            </a>
            <a href="<?= base_url('menu/categories.php') ?>" class="btn btn-outline">
                🏷️ Categories
            </a>
            <a href="<?= base_url('menu/dish-form.php') ?>" class="btn btn-primary">
                ➕ Add New Dish
            </a>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <form method="GET" action="<?= base_url('menu/manage.php') ?>" class="menu-filter-bar">
        <div class="filter-search-wrap">
            <span class="filter-search-icon">🔍</span>
            <input type="text"
                   name="q"
                   value="<?= e($searchQuery) ?>"
                   placeholder="Search dishes by name or description..."
                   class="filter-search-input"
                   aria-label="Search dishes">
        </div>

        <div class="filter-controls">
            <select name="cat" class="filter-select" onchange="this.form.submit()" aria-label="Filter by category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['category_id'] ?>" <?= $categoryFilter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="filter-select" onchange="this.form.submit()" aria-label="Filter by status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Deactivated Only</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filter</button>

            <?php if ($searchQuery !== '' || $categoryFilter !== null || $statusFilter !== 'all'): ?>
                <a href="<?= base_url('menu/manage.php') ?>" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Catalogue Table -->
    <div class="card" style="padding: 0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Item</th>
                        <th>Dish Name & Description</th>
                        <th>Category</th>
                        <th>Price (LKR)</th>
                        <th>Live Stock</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dishes)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 3rem; color: var(--text-muted);">
                                No dishes found matching the current search criteria.
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
                            <tr style="<?= !$isActive ? 'background: #f8fafc; opacity: 0.75;' : '' ?>">
                                <td>
                                    <img src="<?= e($thumb) ?>"
                                         alt="<?= e($d['name']) ?>"
                                         style="width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color);"
                                         onerror="this.onerror=null; this.src='<?= base_url('images/hero_dish.jpg') ?>';">
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.9375rem;">
                                        <a href="<?= base_url('menu/detail.php?id=' . (int)$d['dish_id']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= e($d['name']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 0.8125rem; color: var(--text-muted); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= e($d['description'] ?? 'No description provided.') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size: 0.75rem;">
                                        <?= e($d['category_name']) ?>
                                    </span>
                                </td>
                                <td style="font-weight: 700; color: var(--primary);">
                                    <?= format_lkr((float)$d['price']) ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <?= render_stock_badge($qty, $reorder, true) ?>
                                    </div>
                                    <div class="stock-bar-wrap" style="max-width: 100px; height: 5px;">
                                        <?php
                                        $pct = min(100, (int)(($qty / max(1, $reorder * 4)) * 100));
                                        $clrClass = $qty <= 0 ? 'low' : ($qty <= $reorder ? 'medium' : 'high');
                                        ?>
                                        <div class="stock-bar-fill <?= $clrClass ?>" style="width: <?= max(0, $pct) ?>%;"></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.4rem;">
                                        <a href="<?= base_url('menu/dish-form.php?id=' . (int)$d['dish_id']) ?>"
                                           class="btn btn-outline btn-sm"
                                           title="Edit dish details">
                                            ✏️ Edit
                                        </a>

                                        <?php if ($isActive): ?>
                                            <!-- Soft-Delete Trigger Form -->
                                            <form method="POST" action="<?= base_url('menu/dish-delete.php') ?>" style="display: inline; margin: 0;"
                                                  onsubmit="return confirm('Are you sure you want to deactivate \'<?= e(addslashes($d['name'])) ?>\'? It will be hidden from the public menu.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="dish_id" value="<?= (int)$d['dish_id'] ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger-border);" title="Deactivate dish">
                                                    🗑️ Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Restore Trigger Form -->
                                            <form method="POST" action="<?= base_url('menu/dish-delete.php') ?>" style="display: inline; margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="dish_id" value="<?= (int)$d['dish_id'] ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="btn btn-primary btn-sm" title="Restore to public menu">
                                                    🔄 Restore
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

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
