<?php
// Dish details page.

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/stock.php';
require_once dirname(__DIR__, 2) . '/includes/component_b.php';

$dishId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$dishId || $dishId <= 0) {
    http_response_code(404);
    $pageTitle = 'Dish Not Found';
    include dirname(__DIR__, 2) . '/public/errors/404.php';
    exit;
}

$pdo = get_db_connection();
$dish = get_dish_details($dishId, $pdo);

// Nonexistent or soft-deleted dishes must return 404
if (!$dish || (int)$dish['is_active'] !== 1) {
    http_response_code(404);
    $pageTitle = 'Dish Not Found';
    include dirname(__DIR__, 2) . '/public/errors/404.php';
    exit;
}

$qtyAvailable = (int)$dish['qty_available'];
$reorderLevel = (int)$dish['reorder_level'];
$isOutOfStock = ($qtyAvailable <= 0);
$isLowStock   = ($qtyAvailable > 0 && $qtyAvailable <= $reorderLevel);

$imgUrl = !empty($dish['image_path']) ? base_url($dish['image_path']) : base_url('images/hero_dish.jpg');

$pageTitle = $dish['name'];
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container dish-detail-container">

    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="margin-bottom: 1.5rem; font-size: 0.875rem;">
        <a href="<?= base_url('index.php') ?>" style="color: var(--text-muted); text-decoration: none;">Home</a>
        <span style="margin: 0 0.5rem; color: var(--text-dim);">&rsaquo;</span>
        <a href="<?= base_url('menu/index.php') ?>" style="color: var(--text-muted); text-decoration: none;">Menu</a>
        <span style="margin: 0 0.5rem; color: var(--text-dim);">&rsaquo;</span>
        <a href="<?= base_url('menu/index.php?cat=' . (int)$dish['category_id']) ?>" style="color: var(--text-muted); text-decoration: none;"><?= e($dish['category_name']) ?></a>
        <span style="margin: 0 0.5rem; color: var(--text-dim);">&rsaquo;</span>
        <span style="color: var(--text-main); font-weight: 600;"><?= e($dish['name']) ?></span>
    </nav>

    <!-- Main Dish Detail Layout -->
    <div class="dish-detail-layout">

        <!-- Left Column: Large Image Gallery Showcase -->
        <div class="dish-detail-gallery">
            <img src="<?= e($imgUrl) ?>"
                 alt="<?= e($dish['name']) ?>"
                 onerror="this.onerror=null; this.src='<?= base_url('images/hero_dish.jpg') ?>';">
        </div>

        <!-- Right Column: Dish Info & Inventory Status -->
        <div class="dish-detail-info">
            <div class="dish-detail-meta">
                <span class="badge badge-info" style="font-size: 0.8125rem;">
                    <?= e($dish['category_name']) ?>
                </span>
                <?= render_stock_badge($qtyAvailable, $reorderLevel, true) ?>
            </div>

            <h1 class="dish-detail-title"><?= e($dish['name']) ?></h1>

            <div class="dish-detail-price"><?= format_lkr((float)$dish['price']) ?></div>

            <div class="dish-detail-desc">
                <h3 style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); margin-bottom: 0.5rem;">Description & Culinary Notes</h3>
                <p style="margin: 0;"><?= nl2br(e($dish['description'] ?? 'Freshly prepared specialty dish crafted in our UrbanEats cloud kitchen.')) ?></p>
            </div>

            <!-- Portion Stock Meter -->
            <div class="portion-meter-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);">Live Kitchen Portion Availability</span>
                    <span style="font-weight: 700; font-size: 0.875rem; color: <?= $isOutOfStock ? 'var(--danger)' : ($isLowStock ? 'var(--warning)' : 'var(--success)') ?>;">
                        <?= $isOutOfStock ? 'Sold Out' : "{$qtyAvailable} portions ready" ?>
                    </span>
                </div>

                <div class="stock-bar-wrap" style="max-width: 100%; height: 10px;">
                    <?php
                    $percent = min(100, (int)(($qtyAvailable / max(1, $reorderLevel * 4)) * 100));
                    $class = $isOutOfStock ? 'low' : ($isLowStock ? 'medium' : 'high');
                    ?>
                    <div class="stock-bar-fill <?= $class ?>" style="width: <?= max(0, $percent) ?>%;"></div>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-dim); margin-top: 0.35rem;">
                    <span>Reorder threshold: <?= $reorderLevel ?> portions</span>
                    <span>Synchronized with Kitchen Inventory</span>
                </div>
            </div>

            <!-- Actions -->
            <div style="display: flex; align-items: center; gap: 1rem; margin-top: auto; flex-wrap: wrap;">
                <?php if ($isOutOfStock): ?>
                    <button type="button" class="btn btn-outline" disabled style="opacity: 0.6; cursor: not-allowed;">
                        Currently Sold Out
                    </button>
                <?php else: ?>
                    <a href="<?= base_url('orders/cart.php?add=' . (int)$dish['dish_id']) ?>" class="btn btn-primary btn-lg">
                       Add to Cart
                    </a>
                <?php endif; ?>

                <a href="<?= base_url('menu/index.php') ?>" class="btn btn-outline btn-lg">
                      Back to Catalogue
                </a>
            </div>
        </div>

    </div>

</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
