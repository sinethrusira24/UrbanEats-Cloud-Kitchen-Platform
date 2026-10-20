<?php
/**
 * index.php – Component B: Public Menu & Food Catalogue.
 *
 * Features:
 *   - Category filtering with item counts
 *   - Keyword search across dish title and description
 *   - Secure whitelisted sorting (price asc/desc, name asc/desc, newest)
 *   - Whitelisted pagination
 *   - Real-time stock status badges
 *   - Interactive add-to-cart simulation / customer integration
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/stock.php';

$pageTitle = 'Explore Our Menu';
$db = get_db();

// ── 1. Fetch Active Categories with Dish Counts ───────────────────────────
$catStmt = $db->query(
    'SELECT c.category_id, c.name, COUNT(d.dish_id) AS dish_count
     FROM categories c
     LEFT JOIN dishes d ON c.category_id = d.category_id AND d.is_active = 1
     WHERE c.is_active = 1
     GROUP BY c.category_id, c.name, c.display_order
     ORDER BY c.display_order ASC, c.name ASC'
);
$categories = $catStmt->fetchAll();

// Total active dish count
$totalActiveDishes = (int)$db->query(
    'SELECT COUNT(d.dish_id) 
     FROM dishes d 
     JOIN categories c ON d.category_id = c.category_id 
     WHERE d.is_active = 1 AND c.is_active = 1'
)->fetchColumn();

// ── 2. Validate and Whitelist GET Parameters ──────────────────────────────
$selectedCat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT) ?: null;
$searchQuery = sanitise_string($_GET['q'] ?? '');
$stockFilter = ($_GET['stock'] ?? '') === 'in_stock';

// Whitelist sorting to strictly prevent SQL injection
$sortKey = $_GET['sort'] ?? 'newest';
$sortMap = [
    'price_asc'  => 'd.price ASC',
    'price_desc' => 'd.price DESC',
    'name_asc'   => 'd.name ASC',
    'name_desc'  => 'd.name DESC',
    'newest'     => 'd.dish_id DESC',
];
$orderBy = $sortMap[$sortKey] ?? $sortMap['newest'];

// Category validation
$validCatIds = array_column($categories, 'category_id');
if ($selectedCat !== null && !in_array($selectedCat, $validCatIds, true)) {
    $selectedCat = null;
}

// ── 3. Build Dynamic Prepared Query with PDO ──────────────────────────────
$conditions = ['d.is_active = 1', 'c.is_active = 1'];
$params = [];

if ($selectedCat !== null) {
    $conditions[] = 'd.category_id = :cat_id';
    $params[':cat_id'] = $selectedCat;
}

if ($searchQuery !== '') {
    $conditions[] = '(d.name LIKE :q_name OR d.description LIKE :q_desc)';
    $params[':q_name'] = '%' . $searchQuery . '%';
    $params[':q_desc'] = '%' . $searchQuery . '%';
}

if ($stockFilter) {
    $conditions[] = 'COALESCE(i.qty_available, 0) > 0';
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Count total matching dishes for pagination
$countSql = "SELECT COUNT(d.dish_id) 
             FROM dishes d
             JOIN categories c ON d.category_id = c.category_id
             LEFT JOIN inventory i ON d.dish_id = i.dish_id
             {$whereClause}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();

// Pagination calculation
$perPage = 8;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$pagination = paginate($totalMatching, $perPage, $currentPage);

// Fetch paginated dishes with category & stock info
$dataSql = "SELECT d.dish_id, d.name, d.description, d.price, d.image_path, d.category_id,
                   c.name AS category_name,
                   COALESCE(i.qty_available, 0) AS qty_available,
                   COALESCE(i.reorder_level, 5) AS reorder_level
            FROM dishes d
            JOIN categories c ON d.category_id = c.category_id
            LEFT JOIN inventory i ON d.dish_id = i.dish_id
            {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :offset, :per_page";

$dataStmt = $db->prepare($dataSql);
foreach ($params as $paramKey => $paramVal) {
    $dataStmt->bindValue($paramKey, $paramVal);
}
$dataStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$dataStmt->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$dataStmt->execute();
$dishes = $dataStmt->fetchAll();

// Helper to construct query strings for pagination and filter links
function menu_query(array $overrides = []): string {
    $current = [
        'cat'   => $_GET['cat'] ?? null,
        'q'     => $_GET['q'] ?? null,
        'sort'  => $_GET['sort'] ?? null,
        'stock' => $_GET['stock'] ?? null,
        'page'  => $_GET['page'] ?? null,
    ];
    $merged = array_filter(array_merge($current, $overrides), fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($merged);
}

// Category icon map for visual delight
$categoryIcons = [
    'Rice & Curry'    => '🍛',
    'Kottu Roti'      => '🥘',
    'Burgers & Wraps' => '🍔',
    'Pasta & Noodles' => '🍝',
    'Grilled & BBQ'   => '🥩',
    'Soups & Salads'  => '🥣',
    'Desserts'        => '🍰',
    'Beverages'       => '🍹',
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Header / Banner Section -->
<section style="
  padding: var(--sp-12) 0 var(--sp-8);
  background: radial-gradient(ellipse 70% 60% at 50% -10%, rgba(249,115,22,.2) 0%, transparent 70%), var(--clr-bg);
  border-bottom: 1px solid var(--clr-border);
  position: relative;
">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--sp-4);">
      <div>
        <div style="display:inline-flex;align-items:center;gap:var(--sp-2);background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.3);padding:4px 14px;border-radius:var(--radius-full);font-size:.8125rem;color:var(--clr-primary);font-weight:600;margin-bottom:var(--sp-2);">
          <span>🔥</span> Cloud Kitchen Menu
        </div>
        <h1 style="font-size:clamp(2rem,4.5vw,3rem);margin-bottom:var(--sp-2);">
          Chef's Hand-Crafted <span style="color:var(--clr-primary)">Catalogue</span>
        </h1>
        <p style="color:var(--clr-text-muted);max-width:540px;font-size:1.05rem;">
          Authentic Sri Lankan flavors and global favorites freshly prepared to order.
          Live stock tracking ensures every dish ordered is available.
        </p>
      </div>

      <?php if (has_role(['Kitchen Staff', 'Administrator'])): ?>
        <div class="d-flex gap-3" style="flex-wrap:wrap;">
          <a href="<?= app_url('/menu/manage.php') ?>" class="btn btn-secondary btn-sm">
            ⚙️ Manage Dishes
          </a>
          <a href="<?= app_url('/menu/stock.php') ?>" class="btn btn-primary btn-sm">
            📊 Stock Dashboard
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container" style="padding-top:var(--sp-8);padding-bottom:var(--sp-16);">

  <!-- Category Pills Navigation -->
  <div class="category-pills-wrap" role="navigation" aria-label="Menu Categories">
    <div class="category-pills">
      <a href="<?= menu_query(['cat' => null, 'page' => null]) ?>" 
         class="category-pill <?= $selectedCat === null ? 'active' : '' ?>">
        <span>🍽️</span> All Dishes
        <span class="pill-count"><?= $totalActiveDishes ?></span>
      </a>

      <?php foreach ($categories as $cat): ?>
        <?php 
          $isActive = ($selectedCat === (int)$cat['category_id']);
          $icon = $categoryIcons[$cat['name']] ?? '🍲';
        ?>
        <a href="<?= menu_query(['cat' => $cat['category_id'], 'page' => null]) ?>"
           class="category-pill <?= $isActive ? 'active' : '' ?>">
          <span><?= $icon ?></span> <?= e($cat['name']) ?>
          <span class="pill-count"><?= (int)$cat['dish_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Filter & Search Controls Bar -->
  <form method="GET" action="<?= app_url('/menu/index.php') ?>" class="menu-controls" id="filterForm">
    <?php if ($selectedCat !== null): ?>
      <input type="hidden" name="cat" value="<?= e($selectedCat) ?>">
    <?php endif; ?>

    <!-- Search Input -->
    <div class="search-input-wrap">
      <span class="search-icon" aria-hidden="true">🔍</span>
      <input type="search" 
             name="q" 
             value="<?= e($searchQuery) ?>" 
             placeholder="Search dishes (e.g. Biriyani, Kottu, Burger)..."
             aria-label="Search dishes"
             autocomplete="off">
    </div>

    <!-- Filters Group -->
    <div class="filter-group">
      <!-- Sort Dropdown -->
      <label for="sortSelect" class="sr-only">Sort dishes</label>
      <select name="sort" id="sortSelect" onchange="this.form.submit()">
        <option value="newest"     <?= $sortKey === 'newest'     ? 'selected' : '' ?>>✨ Newest First</option>
        <option value="price_asc"  <?= $sortKey === 'price_asc'  ? 'selected' : '' ?>>💵 Price: Low to High</option>
        <option value="price_desc" <?= $sortKey === 'price_desc' ? 'selected' : '' ?>>💰 Price: High to Low</option>
        <option value="name_asc"   <?= $sortKey === 'name_asc'   ? 'selected' : '' ?>>🔤 Name: A to Z</option>
        <option value="name_desc"  <?= $sortKey === 'name_desc'  ? 'selected' : '' ?>>🔠 Name: Z to A</option>
      </select>

      <!-- In-Stock Only Checkbox -->
      <label style="display:inline-flex;align-items:center;gap:var(--sp-2);cursor:pointer;font-size:.875rem;color:var(--clr-text-muted);user-select:none;">
        <input type="checkbox" 
               name="stock" 
               value="in_stock" 
               <?= $stockFilter ? 'checked' : '' ?> 
               onchange="this.form.submit()">
        <span>🟢 In Stock Only</span>
      </label>

      <button type="submit" class="btn btn-primary btn-sm">Filter</button>

      <?php if ($searchQuery !== '' || $selectedCat !== null || $stockFilter || $sortKey !== 'newest'): ?>
        <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-ghost btn-sm" title="Reset all filters">
          ✕ Reset
        </a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Results Count Summary -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-6);font-size:.9rem;color:var(--clr-text-muted);">
    <div>
      Showing <strong><?= count($dishes) ?></strong> of <strong><?= $totalMatching ?></strong> dishes
      <?php if ($searchQuery !== ''): ?>
        for keyword &ldquo;<span style="color:var(--clr-text)"><?= e($searchQuery) ?></span>&rdquo;
      <?php endif; ?>
    </div>
    <div>
      Page <?= $pagination['current_page'] ?> of <?= max(1, $pagination['last_page']) ?>
    </div>
  </div>

  <!-- Menu Dishes Grid -->
  <?php if (empty($dishes)): ?>
    <div class="card text-center" style="padding:var(--sp-16) var(--sp-6);margin-block:var(--sp-6);">
      <div style="font-size:3.5rem;margin-bottom:var(--sp-4);">🍲</div>
      <h3 style="font-size:1.5rem;margin-bottom:var(--sp-2);">No dishes match your selection</h3>
      <p style="color:var(--clr-text-muted);max-width:440px;margin:0 auto var(--sp-6);">
        Try adjusting your keywords, selecting a different category, or clearing the "In Stock Only" filter.
      </p>
      <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-primary">
        Browse All Dishes
      </a>
    </div>
  <?php else: ?>
    <div class="menu-grid">
      <?php foreach ($dishes as $dish): ?>
        <?php 
          $qty = (int)$dish['qty_available'];
          $reorder = (int)$dish['reorder_level'];
          $isOutOfStock = ($qty <= 0);
          $imgUrl = !empty($dish['image_path']) ? app_url('/' . ltrim($dish['image_path'], '/')) : app_url('/uploads/dishes/biriyani.jpg');
        ?>
        <article class="dish-card" id="dish-card-<?= (int)$dish['dish_id'] ?>">
          
          <!-- Image Container with Overlays -->
          <div class="dish-card-img-wrap">
            <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>" tabindex="-1" aria-hidden="true">
              <img src="<?= e($imgUrl) ?>" 
                   alt="<?= e($dish['name']) ?>" 
                   class="dish-card-img"
                   loading="lazy">
            </a>

            <!-- Category badge overlay -->
            <div class="dish-badge-overlay">
              <span class="badge badge-info" style="font-size:.7rem;backdrop-filter:blur(6px);background:rgba(4,37,51,.85);">
                <?= e($dish['category_name']) ?>
              </span>
            </div>

            <!-- Stock status badge overlay -->
            <div class="dish-stock-overlay">
              <?= render_stock_badge($qty, $reorder, true) ?>
            </div>
          </div>

          <!-- Card Content Body -->
          <div class="dish-card-body">
            <div class="dish-card-category">
              <?= e($dish['category_name']) ?>
            </div>
            
            <h3 class="dish-card-title">
              <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>">
                <?= e($dish['name']) ?>
              </a>
            </h3>

            <p class="dish-card-desc">
              <?= e($dish['description'] ?: 'Delicious dish freshly cooked to perfection using the finest ingredients.') ?>
            </p>

            <div class="dish-card-footer">
              <div class="dish-price-tag">
                <?= format_lkr((float)$dish['price']) ?>
              </div>

              <div class="d-flex gap-2">
                <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>" 
                   class="btn btn-secondary btn-sm"
                   aria-label="View details for <?= e($dish['name']) ?>">
                  View
                </a>

                <?php if ($isOutOfStock): ?>
                  <button type="button" 
                          class="btn btn-sm" 
                          disabled 
                          style="opacity:.4;cursor:not-allowed;"
                          title="Sold out">
                    Sold Out
                  </button>
                <?php else: ?>
                  <button type="button" 
                          class="btn btn-primary btn-sm quick-add-btn" 
                          data-dish-id="<?= (int)$dish['dish_id'] ?>"
                          data-dish-name="<?= e($dish['name']) ?>"
                          data-dish-price="<?= e($dish['price']) ?>"
                          onclick="quickAddToCart(<?= (int)$dish['dish_id'] ?>, '<?= e(addslashes($dish['name'])) ?>')">
                    + Order
                  </button>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Pagination Controls -->
    <?php if ($pagination['last_page'] > 1): ?>
      <nav class="pagination" aria-label="Menu pagination">
        <!-- Prev Button -->
        <?php if ($pagination['current_page'] > 1): ?>
          <a href="<?= menu_query(['page' => $pagination['current_page'] - 1]) ?>" 
             class="page-link" 
             aria-label="Previous page">‹</a>
        <?php else: ?>
          <span class="page-link disabled" aria-disabled="true">‹</span>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
          <?php if ($p === $pagination['current_page']): ?>
            <span class="page-link active" aria-current="page"><?= $p ?></span>
          <?php elseif ($p === 1 || $p === $pagination['last_page'] || abs($p - $pagination['current_page']) <= 2): ?>
            <a href="<?= menu_query(['page' => $p]) ?>" class="page-link"><?= $p ?></a>
          <?php elseif (abs($p - $pagination['current_page']) === 3): ?>
            <span class="page-link disabled" style="border:none;background:none;">…</span>
          <?php endif; ?>
        <?php endfor; ?>

        <!-- Next Button -->
        <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
          <a href="<?= menu_query(['page' => $pagination['current_page'] + 1]) ?>" 
             class="page-link" 
             aria-label="Next page">›</a>
        <?php else: ?>
          <span class="page-link disabled" aria-disabled="true">›</span>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- Quick Add to Cart Client Enhancement Script -->
<script>
function quickAddToCart(dishId, dishName) {
  // Store simulated cart in localStorage / session
  let cart = JSON.parse(localStorage.getItem('urbaneats_cart') || '[]');
  let existing = cart.find(i => i.id === dishId);
  if (existing) {
    existing.qty += 1;
  } else {
    cart.push({ id: dishId, name: dishName, qty: 1 });
  }
  localStorage.setItem('urbaneats_cart', JSON.stringify(cart));

  // Show modern toast notification
  if (typeof showToast === 'function') {
    showToast(`Added ${dishName} to your order!`, 'success');
  } else {
    const container = document.getElementById('toast-container');
    if (container) {
      const toast = document.createElement('div');
      toast.className = 'toast';
      toast.innerHTML = `<span style="font-size:1.2rem">🛒</span> <div><strong>${dishName}</strong> added to cart!</div>`;
      container.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
