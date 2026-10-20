<?php
/**
 * index.php – Public landing page / menu home.
 * Component B (Menu & Catalogue) will expand this page.
 * Component A provides the shared layout and user context.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Home';
include __DIR__ . '/../includes/header.php';
?>

<section style="
  padding: var(--sp-16) 0;
  text-align: center;
  background:
    radial-gradient(ellipse 70% 50% at 50% -10%, rgba(249,115,22,.18) 0%, transparent 60%),
    var(--clr-bg);
  border-bottom: 1px solid var(--clr-border);
">
  <div class="container">
    <h1 style="font-size:clamp(2.5rem,6vw,4rem);margin-bottom:var(--sp-5)">
      Cloud Kitchen, <span style="color:var(--clr-primary)">Delivered.</span>
    </h1>
    <p style="font-size:1.2rem;max-width:560px;margin:0 auto var(--sp-8);color:var(--clr-text-muted)">
      Fresh, chef-crafted meals from UrbanEats delivered to your door across Sri Lanka.
      Order online, track in real time.
    </p>
    <div class="d-flex gap-4" style="justify-content:center;flex-wrap:wrap">
      <?php if (!is_logged_in()): ?>
        <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-primary btn-lg">
          🍽️ Browse Menu
        </a>
        <a href="<?= app_url('/auth/login.php') ?>" class="btn btn-secondary btn-lg">
          Sign In
        </a>
      <?php else: ?>
        <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-primary btn-lg">
          🍽️ Browse Menu
        </a>
        <?php if (has_role('Customer')): ?>
          <a href="<?= app_url('/orders/cart.php') ?>" class="btn btn-secondary btn-lg">
            🛒 My Cart
          </a>
        <?php elseif (has_role('Administrator')): ?>
          <a href="<?= app_url('/admin/dashboard.php') ?>" class="btn btn-secondary btn-lg">
            📊 Dashboard
          </a>
        <?php elseif (has_role('Kitchen Staff')): ?>
          <a href="<?= app_url('/menu/stock.php') ?>" class="btn btn-secondary btn-lg">
            📊 Stock Dashboard
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
// Component B: Live Menu & Categories Section
require_once __DIR__ . '/../includes/stock.php';
$db = get_db();

// Fetch categories with active dish count
$featuredCats = $db->query(
    'SELECT c.category_id, c.name, COUNT(d.dish_id) AS dish_count
     FROM categories c
     LEFT JOIN dishes d ON c.category_id = d.category_id AND d.is_active = 1
     WHERE c.is_active = 1
     GROUP BY c.category_id, c.name, c.display_order
     ORDER BY c.display_order ASC
     LIMIT 8'
)->fetchAll();

// Fetch featured chef specials (active dishes with inventory)
$featuredDishes = $db->query(
    'SELECT d.dish_id, d.name, d.description, d.price, d.image_path, c.name AS category_name,
            COALESCE(i.qty_available, 0) AS qty_available,
            COALESCE(i.reorder_level, 5) AS reorder_level
     FROM dishes d
     JOIN categories c ON d.category_id = c.category_id
     LEFT JOIN inventory i ON d.dish_id = i.dish_id
     WHERE d.is_active = 1 AND c.is_active = 1
     ORDER BY d.dish_id ASC
     LIMIT 6'
)->fetchAll();

$catIcons = [
    'Rice & Curry'    => '🍛',
    'Kottu Roti'      => '🥘',
    'Burgers & Wraps' => '🍔',
    'Pasta & Noodles' => '🍝',
    'Grilled & BBQ'   => '🥩',
    'Soups & Salads'  => '🥣',
    'Desserts'        => '🍰',
    'Beverages'       => '🍹',
];
?>

<!-- Component B: Browse by Category Section -->
<section style="padding:var(--sp-12) 0;border-bottom:1px solid var(--clr-border);">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-6);flex-wrap:wrap;gap:var(--sp-2);">
      <div>
        <div style="font-size:.8125rem;color:var(--clr-primary);font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
          Explore Catalogue
        </div>
        <h2 style="font-size:1.75rem;margin-top:2px;">Popular Categories</h2>
      </div>
      <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-ghost btn-sm">
        View All Categories &rarr;
      </a>
    </div>

    <div class="category-pills">
      <a href="<?= app_url('/menu/index.php') ?>" class="category-pill active">
        <span>🍽️</span> All Menu
      </a>
      <?php foreach ($featuredCats as $c): ?>
        <a href="<?= app_url('/menu/index.php?cat=' . (int)$c['category_id']) ?>" class="category-pill">
          <span><?= $catIcons[$c['name']] ?? '🍲' ?></span>
          <?= e($c['name']) ?>
          <span class="pill-count"><?= (int)$c['dish_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Component B: Featured Dishes Section -->
<section style="padding:var(--sp-16) 0;background:var(--clr-bg);">
  <div class="container">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:var(--sp-8);flex-wrap:wrap;gap:var(--sp-4);">
      <div>
        <div style="font-size:.8125rem;color:var(--clr-primary);font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
          Chef's Signatures
        </div>
        <h2 style="font-size:2rem;margin-top:2px;">Featured Cloud Kitchen Dishes</h2>
      </div>
      <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-secondary">
        Browse Entire Menu (22+ Dishes) &rarr;
      </a>
    </div>

    <div class="menu-grid">
      <?php foreach ($featuredDishes as $dish): ?>
        <?php 
          $qty = (int)$dish['qty_available'];
          $reorder = (int)$dish['reorder_level'];
          $isOutOfStock = ($qty <= 0);
          $imgUrl = !empty($dish['image_path']) ? app_url('/' . ltrim($dish['image_path'], '/')) : app_url('/uploads/dishes/biriyani.jpg');
        ?>
        <article class="dish-card">
          <div class="dish-card-img-wrap">
            <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>">
              <img src="<?= e($imgUrl) ?>" alt="<?= e($dish['name']) ?>" class="dish-card-img" loading="lazy">
            </a>
            <div class="dish-badge-overlay">
              <span class="badge badge-info" style="font-size:.7rem;backdrop-filter:blur(6px);background:rgba(4,37,51,.85);">
                <?= e($dish['category_name']) ?>
              </span>
            </div>
            <div class="dish-stock-overlay">
              <?= render_stock_badge($qty, $reorder, true) ?>
            </div>
          </div>

          <div class="dish-card-body">
            <div class="dish-card-category"><?= e($dish['category_name']) ?></div>
            <h3 class="dish-card-title">
              <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>">
                <?= e($dish['name']) ?>
              </a>
            </h3>
            <p class="dish-card-desc"><?= e($dish['description']) ?></p>

            <div class="dish-card-footer">
              <div class="dish-price-tag"><?= format_lkr((float)$dish['price']) ?></div>
              <div class="d-flex gap-2">
                <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>" class="btn btn-secondary btn-sm">
                  Details
                </a>
                <?php if ($isOutOfStock): ?>
                  <button type="button" class="btn btn-sm" disabled style="opacity:.4;">Sold Out</button>
                <?php else: ?>
                  <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>" class="btn btn-primary btn-sm">
                    Order
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- Feature highlights -->
<section style="padding:var(--sp-16) 0">
  <div class="container">
    <h2 class="text-center" style="margin-bottom:var(--sp-10)">Why UrbanEats?</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:var(--sp-6)">

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">🏃</div>
        <h3>Fast Delivery</h3>
        <p>Real-time order tracking from kitchen to your door.</p>
      </div>

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">🍛</div>
        <h3>Fresh Dishes</h3>
        <p>Chef-crafted Sri Lankan and international cuisine, prepared fresh daily.</p>
      </div>

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">💳</div>
        <h3>Easy Payment</h3>
        <p>Cash on delivery, card, or bank transfer — your choice.</p>
      </div>

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">📦</div>
        <h3>Order History</h3>
        <p>All your past orders in one place, reorder in one click.</p>
      </div>

    </div>
  </div>
</section>

<!-- CTA -->
<?php if (!is_logged_in()): ?>
<section style="padding:var(--sp-12) 0;background:var(--clr-bg-card);border-top:1px solid var(--clr-border);border-bottom:1px solid var(--clr-border)">
  <div class="container text-center">
    <h2 style="margin-bottom:var(--sp-4)">Ready to order?</h2>
    <p style="margin-bottom:var(--sp-6)">Create your free account and start enjoying UrbanEats today.</p>
    <a href="<?= app_url('/auth/register.php') ?>" class="btn btn-primary btn-lg">
      Create Free Account
    </a>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
