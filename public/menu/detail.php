<?php
/**
 * detail.php – Component B: Dish Detail Page.
 *
 * Displays:
 *   - High-resolution photo
 *   - Detailed culinary description
 *   - Category badge & pricing in LKR
 *   - Real-time stock availability status and live portions meter
 *   - Interactive quantity stepper and order action
 *   - Administrative quick links for Kitchen Staff and Admins
 *   - Related dishes in the same category
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/stock.php';

$dishId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$dishId) {
    http_response_code(404);
    include __DIR__ . '/../../includes/errors/404.php';
    exit;
}

$isStaff = has_role(['Kitchen Staff', 'Administrator']);
$dish = get_dish($dishId, !$isStaff);

if (!$dish) {
    http_response_code(404);
    include __DIR__ . '/../../includes/errors/404.php';
    exit;
}

$pageTitle = $dish['name'] . ' – UrbanEats';
$db = get_db();

// ── Fetch Related Dishes in the same Category ──────────────────────────────
$relStmt = $db->prepare(
    'SELECT d.dish_id, d.name, d.price, d.image_path, c.name AS category_name,
            COALESCE(i.qty_available, 0) AS qty_available,
            COALESCE(i.reorder_level, 5) AS reorder_level
     FROM dishes d
     JOIN categories c ON d.category_id = c.category_id
     LEFT JOIN inventory i ON d.dish_id = i.dish_id
     WHERE d.category_id = :cat_id AND d.dish_id != :dish_id AND d.is_active = 1
     ORDER BY d.dish_id DESC
     LIMIT 3'
);
$relStmt->execute([
    ':cat_id'  => $dish['category_id'],
    ':dish_id' => $dishId,
]);
$relatedDishes = $relStmt->fetchAll();

$qty = (int)$dish['qty_available'];
$reorder = (int)$dish['reorder_level'];
$isOutOfStock = ($qty <= 0);
$isLowStock   = ($qty > 0 && $qty <= $reorder);
$imgUrl = !empty($dish['image_path']) ? app_url('/' . ltrim($dish['image_path'], '/')) : app_url('/uploads/dishes/biriyani.jpg');

include __DIR__ . '/../../includes/header.php';
?>

<div class="container dish-detail-container">

  <!-- Breadcrumb Navigation -->
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= app_url('/index.php') ?>">Home</a>
    <span class="breadcrumb-separator" aria-hidden="true">›</span>
    <a href="<?= app_url('/menu/index.php') ?>">Menu</a>
    <span class="breadcrumb-separator" aria-hidden="true">›</span>
    <a href="<?= app_url('/menu/index.php?cat=' . (int)$dish['category_id']) ?>"><?= e($dish['category_name']) ?></a>
    <span class="breadcrumb-separator" aria-hidden="true">›</span>
    <span style="color:var(--clr-text)" aria-current="page"><?= e($dish['name']) ?></span>
  </nav>

  <!-- Staff Quick Management Bar -->
  <?php if ($isStaff): ?>
    <div style="background:rgba(20,184,166,.1);border:1px solid rgba(20,184,166,.3);border-radius:var(--radius-md);padding:var(--sp-3) var(--sp-5);margin-bottom:var(--sp-6);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--sp-3);">
      <div class="d-flex align-center gap-2">
        <span style="font-size:1.2rem;">👨‍🍳</span>
        <span style="font-size:.9rem;color:var(--clr-secondary);font-weight:600;">Staff Management Mode:</span>
        <span style="font-size:.85rem;color:var(--clr-text-muted);">
          Status: <?= $dish['is_active'] ? '🟢 Active' : '🔴 Soft-Deleted (Hidden from customers)' ?> |
          Stock: <?= $qty ?> portions (Reorder: <?= $reorder ?>)
        </span>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= app_url('/menu/dish-form.php?id=' . $dishId) ?>" class="btn btn-secondary btn-sm">
          ✏️ Edit Dish
        </a>
        <a href="<?= app_url('/menu/stock.php?q=' . urlencode($dish['name'])) ?>" class="btn btn-ghost btn-sm">
          📊 Manage Stock
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Main Split Layout -->
  <div class="dish-detail-layout">

    <!-- Left Media Gallery -->
    <div class="dish-detail-media">
      <img src="<?= e($imgUrl) ?>" 
           alt="<?= e($dish['name']) ?>" 
           class="dish-detail-img"
           id="mainDishImage">

      <div class="dish-badge-overlay" style="top:var(--sp-4);left:var(--sp-4);">
        <span class="badge badge-info" style="font-size:.8125rem;backdrop-filter:blur(8px);background:rgba(4,37,51,.85);">
          <?= e($dish['category_name']) ?>
        </span>
      </div>

      <div class="dish-stock-overlay" style="top:var(--sp-4);right:var(--sp-4);">
        <?= render_stock_badge($qty, $reorder, true) ?>
      </div>
    </div>

    <!-- Right Content Info -->
    <div class="dish-detail-content">
      <div>
        <div class="dish-detail-header">
          <span style="color:var(--clr-primary);font-size:.875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
            UrbanEats Kitchen Signature
          </span>
          <h1 style="font-size:clamp(1.75rem,3.5vw,2.5rem);margin-top:var(--sp-2);line-height:1.2;">
            <?= e($dish['name']) ?>
          </h1>
        </div>

        <div class="dish-detail-price">
          <?= format_lkr((float)$dish['price']) ?>
        </div>

        <div style="font-size:1.05rem;color:var(--clr-text-muted);line-height:1.7;margin-bottom:var(--sp-6);">
          <p><?= nl2br(e($dish['description'] ?: 'Crafted with premium authentic ingredients, perfectly balanced spices, and cooked fresh upon receiving your order in our certified cloud kitchen.')) ?></p>
        </div>

        <!-- Real-Time Stock & Availability Card -->
        <div class="dish-availability-card">
          <div>
            <div style="font-size:.85rem;color:var(--clr-text-muted);margin-bottom:var(--sp-1);">Current Availability</div>
            <?php if ($isOutOfStock): ?>
              <div style="color:var(--clr-error);font-weight:700;display:flex;align-items:center;gap:var(--sp-2);">
                <span class="stock-dot" style="background:var(--clr-error)"></span>
                Currently Sold Out
              </div>
              <div style="font-size:.8rem;color:var(--clr-text-muted);margin-top:2px;">
                Our kitchen has run out of ingredients for this item today.
              </div>
            <?php elseif ($isLowStock): ?>
              <div style="color:var(--clr-warning);font-weight:700;display:flex;align-items:center;gap:var(--sp-2);">
                <span class="stock-dot pulse" style="background:var(--clr-warning)"></span>
                High Demand – Only <?= $qty ?> portions left!
              </div>
              <div style="font-size:.8rem;color:var(--clr-text-muted);margin-top:2px;">
                Order promptly before it sells out for today's service.
              </div>
            <?php else: ?>
              <div style="color:var(--clr-success);font-weight:700;display:flex;align-items:center;gap:var(--sp-2);">
                <span class="stock-dot" style="background:var(--clr-success)"></span>
                In Stock & Ready for Prep (<?= $qty ?> available)
              </div>
              <div style="font-size:.8rem;color:var(--clr-text-muted);margin-top:2px;">
                Freshly prepared and dispatched within 20-30 minutes.
              </div>
            <?php endif; ?>
          </div>

          <div>
            <?= render_stock_badge($qty, $reorder, false) ?>
          </div>
        </div>

        <!-- Order / Quantity Actions -->
        <?php if ($isOutOfStock): ?>
          <div style="background:var(--clr-bg-input);border:1px dashed var(--clr-border);border-radius:var(--radius-md);padding:var(--sp-4);text-align:center;">
            <p style="color:var(--clr-text-muted);margin-bottom:var(--sp-3);">
              This dish is currently out of stock. Please check back later or explore other dishes in the same category below.
            </p>
            <a href="<?= app_url('/menu/index.php?cat=' . (int)$dish['category_id']) ?>" class="btn btn-secondary btn-sm">
              Explore More <?= e($dish['category_name']) ?>
            </a>
          </div>
        <?php else: ?>
          <div class="d-flex align-center gap-4" style="flex-wrap:wrap;margin-bottom:var(--sp-8);">
            <!-- Quantity Stepper -->
            <div class="qty-stepper" role="group" aria-label="Quantity selector">
              <button type="button" class="qty-stepper-btn" onclick="stepQty(-1)" aria-label="Decrease quantity">−</button>
              <input type="number" id="orderQty" class="qty-stepper-input" value="1" min="1" max="<?= $qty ?>" readonly aria-label="Selected quantity">
              <button type="button" class="qty-stepper-btn" onclick="stepQty(1)" aria-label="Increase quantity">+</button>
            </div>

            <!-- Add to Cart Action -->
            <button type="button" 
                    class="btn btn-primary btn-lg" 
                    id="addToOrderBtn"
                    style="flex:1;min-width:200px;"
                    onclick="addDetailToOrder()">
              🛒 Add to Order &bull; <span id="btnTotalText"><?= format_lkr((float)$dish['price']) ?></span>
            </button>
          </div>
        <?php endif; ?>

      </div>

      <!-- Trust & Kitchen Highlights -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:var(--sp-3);padding-top:var(--sp-6);border-top:1px solid var(--clr-border);">
        <div style="display:flex;align-items:center;gap:var(--sp-2);font-size:.8125rem;color:var(--clr-text-muted);">
          <span style="font-size:1.2rem;">⚡</span>
          <span>Fast Delivery ~30 min</span>
        </div>
        <div style="display:flex;align-items:center;gap:var(--sp-2);font-size:.8125rem;color:var(--clr-text-muted);">
          <span style="font-size:1.2rem;">🧑‍🍳</span>
          <span>Freshly Prepared</span>
        </div>
        <div style="display:flex;align-items:center;gap:var(--sp-2);font-size:.8125rem;color:var(--clr-text-muted);">
          <span style="font-size:1.2rem;">🛡️</span>
          <span>Hygienic Cloud Kitchen</span>
        </div>
      </div>

    </div>
  </div>

  <!-- Related Dishes Section -->
  <?php if (!empty($relatedDishes)): ?>
    <section style="margin-top:var(--sp-12);">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-6);">
        <div>
          <span style="color:var(--clr-primary);font-size:.8125rem;font-weight:700;text-transform:uppercase;">More Suggestions</span>
          <h2 style="font-size:1.5rem;margin-top:4px;">You Might Also Enjoy</h2>
        </div>
        <a href="<?= app_url('/menu/index.php?cat=' . (int)$dish['category_id']) ?>" class="btn btn-ghost btn-sm">
          View All <?= e($dish['category_name']) ?> &rarr;
        </a>
      </div>

      <div class="menu-grid">
        <?php foreach ($relatedDishes as $rel): ?>
          <?php 
            $relQty = (int)$rel['qty_available'];
            $relReorder = (int)$rel['reorder_level'];
            $relImg = !empty($rel['image_path']) ? app_url('/' . ltrim($rel['image_path'], '/')) : app_url('/uploads/dishes/biriyani.jpg');
          ?>
          <article class="dish-card">
            <div class="dish-card-img-wrap">
              <a href="<?= app_url('/menu/detail.php?id=' . (int)$rel['dish_id']) ?>" tabindex="-1">
                <img src="<?= e($relImg) ?>" alt="<?= e($rel['name']) ?>" class="dish-card-img" loading="lazy">
              </a>
              <div class="dish-stock-overlay">
                <?= render_stock_badge($relQty, $relReorder, true) ?>
              </div>
            </div>
            <div class="dish-card-body">
              <h3 class="dish-card-title">
                <a href="<?= app_url('/menu/detail.php?id=' . (int)$rel['dish_id']) ?>">
                  <?= e($rel['name']) ?>
                </a>
              </h3>
              <div class="dish-card-footer">
                <div class="dish-price-tag">
                  <?= format_lkr((float)$rel['price']) ?>
                </div>
                <a href="<?= app_url('/menu/detail.php?id=' . (int)$rel['dish_id']) ?>" class="btn btn-secondary btn-sm">
                  View Dish
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</div>

<script>
const unitPrice = <?= (float)$dish['price'] ?>;
const maxStock = <?= $qty ?>;
const dishId = <?= $dishId ?>;
const dishName = <?= json_encode($dish['name']) ?>;

function stepQty(delta) {
  const input = document.getElementById('orderQty');
  let val = parseInt(input.value, 10) + delta;
  if (val < 1) val = 1;
  if (val > maxStock) val = maxStock;
  input.value = val;

  const total = (val * unitPrice).toFixed(2);
  const text = 'LKR ' + Number(total).toLocaleString('en-US', {minimumFractionDigits: 2});
  const btnText = document.getElementById('btnTotalText');
  if (btnText) btnText.textContent = text;
}

function addDetailToOrder() {
  const qtyInput = document.getElementById('orderQty');
  const qty = parseInt(qtyInput.value, 10) || 1;

  let cart = JSON.parse(localStorage.getItem('urbaneats_cart') || '[]');
  let item = cart.find(i => i.id === dishId);
  if (item) {
    item.qty = Math.min(item.qty + qty, maxStock);
  } else {
    cart.push({ id: dishId, name: dishName, price: unitPrice, qty: qty });
  }
  localStorage.setItem('urbaneats_cart', JSON.stringify(cart));

  const container = document.getElementById('toast-container');
  if (container) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<span style="font-size:1.3rem">🛒</span> <div><strong>${qty}x ${dishName}</strong> added to your cart!</div>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
