<?php
// Customer shopping cart page.

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/component_c.php';

// 1. Handle GET query parameters for adding dishes
// Supports direct integration from Component B menu/detail.php (orders/cart.php?add=ID)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dishId = 0;
    if (!empty($_GET['add'])) {
        $dishId = (int)$_GET['add'];
    } elseif (isset($_GET['action']) && $_GET['action'] === 'add' && !empty($_GET['dish_id'])) {
        $dishId = (int)$_GET['dish_id'];
    }

    if ($dishId > 0) {
        $qty = max(1, (int)($_GET['qty'] ?? $_GET['quantity'] ?? 1));
        $result = cart_add($dishId, $qty);
        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('danger', $result['message']);
        }
        // Redirect cleanly so page refresh does not re-add the item
        $returnUrl = !empty($_GET['return']) ? (string)$_GET['return'] : 'orders/cart.php';
        redirect($returnUrl);
    }
}

// 2. Handle POST actions for Cart Mutations (CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $dishId = (int)($_POST['dish_id'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 1);
            $result = cart_add($dishId, $qty);
            if ($result['success']) {
                flash('success', $result['message']);
            } else {
                flash('danger', $result['message']);
            }
            $returnTo = $_POST['return_url'] ?? base_url('orders/cart.php');
            redirect($returnTo);
            break;

        case 'update':
            $dishId = (int)($_POST['dish_id'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 0);
            $result = cart_update($dishId, $qty);
            if ($result['success']) {
                flash('success', $result['message']);
            } else {
                flash('danger', $result['message']);
            }
            redirect('orders/cart.php');
            break;

        case 'remove':
            $dishId = (int)($_POST['dish_id'] ?? 0);
            cart_remove($dishId);
            flash('info', 'Item removed from your cart.');
            redirect('orders/cart.php');
            break;

        case 'clear':
            cart_clear();
            flash('info', 'Your shopping cart has been cleared.');
            redirect('orders/cart.php');
            break;

        default:
            flash('warning', 'Unknown cart operation.');
            redirect('orders/cart.php');
            break;
    }
}

// Fetch up-to-date cart details from database
$cartDetails = cart_get_details();
$items = $cartDetails['items'];
$subtotal = $cartDetails['subtotal'];
$totalCount = $cartDetails['count'];
$hasUnavailable = $cartDetails['has_unavailable'];

// Fetch quick-add popular dishes from menu
$pdo = get_db_connection();
$stmtPopular = $pdo->query(
    'SELECT d.dish_id, d.name, d.price, d.image_path, c.name AS category_name,
            COALESCE(i.qty_available, 0) AS qty_available
     FROM dishes d
     JOIN categories c ON d.category_id = c.category_id
     LEFT JOIN inventory i ON d.dish_id = i.dish_id
     WHERE d.is_active = 1 AND COALESCE(i.qty_available, 0) > 0
     ORDER BY d.dish_id ASC
     LIMIT 4'
);
$popularDishes = $stmtPopular->fetchAll();

$pageTitle = 'Your Shopping Cart';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="c-page-header">
    <div>
        <h1 class="c-page-title">
            <span>🛒</span> Shopping Cart
        </h1>
        <p class="c-page-subtitle">Review your selected dishes before proceeding to checkout.</p>
    </div>
    <?php if (!empty($items)): ?>
        <form action="<?= base_url('orders/cart.php') ?>" method="POST" onsubmit="return confirm('Are you sure you want to empty your entire cart?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--c-danger); border-color: #FECACA;">
                Clear All
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($items)): ?>
    <div class="c-card c-cart-empty-state">
        <div class="c-cart-empty-icon">🍽️</div>
        <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; color: var(--c-charcoal);">Your cart is empty</h2>
        <p style="color: var(--c-muted); max-width: 420px; margin: 0 auto 1.5rem auto;">
            Explore our chef-crafted menu featuring authentic Sri Lankan curries, signature kottu, biryanis, and quick bites.
        </p>
        <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
            Explore Entire Menu
        </a>
    </div>

    <!-- Quick Add Popular Dishes Section -->
    <?php if (!empty($popularDishes)): ?>
        <div style="margin-top: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--c-charcoal); margin: 0;">
                    🔥 Quick Add Popular Dishes
                </h3>
                <a href="<?= base_url('menu/index.php') ?>" style="font-size: 0.85rem; color: var(--c-primary); font-weight: 600; text-decoration: none;">
                    View All Dishes →
                </a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
                <?php foreach ($popularDishes as $pDish): ?>
                    <div class="c-card" style="margin-bottom: 0; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span style="font-size: 0.75rem; color: var(--c-muted); text-transform: uppercase; font-weight: 600;"><?= e($pDish['category_name']) ?></span>
                            <h4 style="margin: 0.35rem 0 0.5rem 0; font-size: 1.05rem; color: var(--c-charcoal);"><?= e($pDish['name']) ?></h4>
                            <div style="font-weight: 700; color: var(--c-primary); font-size: 1.1rem; margin-bottom: 1rem;">
                                <?= format_lkr($pDish['price']) ?>
                            </div>
                        </div>

                        <form action="<?= base_url('orders/cart.php') ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="dish_id" value="<?= (int)$pDish['dish_id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="c-btn-block c-btn-primary" style="padding: 0.55rem 1rem; font-size: 0.9rem;">
                                + Add to Cart
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>

    <?php if ($hasUnavailable): ?>
        <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem;">
            <span>⚠️</span>
            <div>One or more items in your cart are currently out of stock or inactive. Please adjust quantities before checking out.</div>
        </div>
    <?php endif; ?>

    <div class="c-two-column">
        <!-- Left Column: Items Table -->
        <div class="c-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--c-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 class="c-card-title">Selected Dishes (<?= $totalCount ?>)</h3>
                <a href="<?= base_url('menu/index.php') ?>" style="font-size: 0.9rem; color: var(--c-primary); font-weight: 600; text-decoration: none;">
                    + Add More Items
                </a>
            </div>

            <div style="overflow-x: auto;">
                <table class="c-cart-table">
                    <thead>
                        <tr>
                            <th>Dish</th>
                            <th style="width: 120px;">Price</th>
                            <th style="width: 150px; text-align: center;">Quantity</th>
                            <th style="width: 120px; text-align: right;">Subtotal</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr class="c-cart-item <?= !$item['is_available'] ? 'style="opacity: 0.65; background: #FFF5F5;"' : '' ?>">
                                <td>
                                    <div class="c-cart-dish-cell">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="<?= base_url($item['image_path']) ?>" alt="<?= e($item['name']) ?>" class="c-cart-thumb" onerror="this.src='<?= base_url('images/placeholder-dish.jpg') ?>';">
                                        <?php else: ?>
                                            <div class="c-cart-thumb" style="display:flex;align-items:center;justify-content:center;color:#9CA3AF;font-size:1.5rem;">🍲</div>
                                        <?php endif; ?>
                                        <div class="c-cart-dish-meta">
                                            <h4><?= e($item['name']) ?></h4>
                                            <span class="category"><?= e($item['category_name']) ?></span>
                                            <?php if (!empty($item['status_warning'])): ?>
                                                <div style="color: var(--c-danger); font-size: 0.8rem; font-weight: 600; margin-top: 0.25rem;">
                                                    ⚠️ <?= e($item['status_warning']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--c-charcoal);"><?= format_lkr($item['unit_price']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <form action="<?= base_url('orders/cart.php') ?>" method="POST" class="c-qty-form" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="dish_id" value="<?= (int)$item['dish_id'] ?>">
                                        <div class="c-qty-control">
                                            <button type="button" class="c-qty-btn" onclick="adjustQty(this, -1)">−</button>
                                            <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>" min="1" max="50" class="c-qty-input" onchange="this.form.submit()">
                                            <button type="button" class="c-qty-btn" onclick="adjustQty(this, 1)">+</button>
                                        </div>
                                    </form>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--c-charcoal);">
                                    <?= format_lkr($item['line_total']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <form action="<?= base_url('orders/cart.php') ?>" method="POST" onsubmit="return confirm('Remove <?= e($item['name']) ?> from your cart?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="dish_id" value="<?= (int)$item['dish_id'] ?>">
                                        <button type="submit" class="c-remove-btn" title="Remove item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="padding: 1rem 1.5rem; background: #FAFDFB; border-top: 1px solid var(--c-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <a href="<?= base_url('menu/index.php') ?>" class="btn btn-outline btn-sm">
                    ← Back to Menu
                </a>
                <span style="font-size: 0.85rem; color: var(--c-muted);">
                    Prices are shown in Sri Lankan Rupees (LKR) with all standard kitchen taxes included.
                </span>
            </div>
        </div>

        <!-- Right Column: Order Summary -->
        <div>
            <div class="c-card">
                <h3 class="c-card-title" style="margin-bottom: 1rem;">Order Summary</h3>

                <div class="c-summary-row">
                    <span>Total Portions</span>
                    <span style="font-weight: 600; color: var(--c-charcoal);"><?= $totalCount ?> items</span>
                </div>
                <div class="c-summary-row">
                    <span>Subtotal</span>
                    <span style="font-weight: 600; color: var(--c-charcoal);"><?= format_lkr($subtotal) ?></span>
                </div>
                <div class="c-summary-row">
                    <span>Estimated Kitchen Prep</span>
                    <span style="color: var(--c-primary); font-weight: 600;">20 – 30 mins</span>
                </div>

                <div class="c-summary-row total">
                    <span>Estimated Total</span>
                    <span><?= format_lkr($subtotal) ?></span>
                </div>

                <div style="margin-top: 1.5rem;">
                    <?php if ($hasUnavailable): ?>
                        <button type="button" class="c-btn-block c-btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">
                            Adjust Unavailable Items
                        </button>
                        <p style="text-align: center; font-size: 0.8rem; color: var(--c-danger); margin-top: 0.5rem;">
                            Please resolve stock issues before proceeding.
                        </p>
                    <?php else: ?>
                        <a href="<?= base_url('orders/checkout.php') ?>" class="c-btn-block c-btn-primary">
                            Proceed to Checkout →
                        </a>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--c-border); font-size: 0.85rem; color: var(--c-muted); text-align: center;">
                    🔒 Safe & secure simulated checkout.<br>Coupons can be applied on the next step.
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
function adjustQty(button, change) {
    const form = button.closest('form');
    const input = form.querySelector('input[name="quantity"]');
    let val = parseInt(input.value) || 1;
    val += change;
    if (val < 1) val = 1;
    if (val > 50) val = 50;
    input.value = val;
    form.submit();
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
