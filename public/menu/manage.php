<?php
/**
 * manage.php – Component B: Staff Dish Catalogue Management.
 *
 * Restricted to Kitchen Staff & Administrator.
 * Full CRUD interface with soft-delete / restore actions.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/stock.php';

require_role(['Kitchen Staff', 'Administrator']);

$pageTitle = 'Manage Dishes & Catalogue';
$db = get_db();

// ── 1. Fetch Categories for Filter ─────────────────────────────────────────
$categories = $db->query(
    'SELECT category_id, name FROM categories ORDER BY display_order ASC, name ASC'
)->fetchAll();

// ── 2. Filters & Whitelisting ──────────────────────────────────────────────
$selectedCat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT) ?: null;
$searchQuery = sanitise_string($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all'; // all | active | inactive

$conditions = [];
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

if ($statusFilter === 'active') {
    $conditions[] = 'd.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $conditions[] = 'd.is_active = 0';
}

$whereClause = !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// ── 3. KPI Statistics ──────────────────────────────────────────────────────
$stats = $db->query(
    'SELECT 
       COUNT(*) AS total,
       SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS active_count,
       SUM(CASE WHEN d.is_active = 0 THEN 1 ELSE 0 END) AS inactive_count,
       SUM(CASE WHEN d.is_active = 1 AND COALESCE(i.qty_available, 0) <= 0 THEN 1 ELSE 0 END) AS out_of_stock
     FROM dishes d
     LEFT JOIN inventory i ON d.dish_id = i.dish_id'
)->fetch();

// ── 4. Pagination ──────────────────────────────────────────────────────────
$countSql = "SELECT COUNT(*) FROM dishes d {$whereClause}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();

$perPage = 10;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$pagination = paginate($totalMatching, $perPage, $currentPage);

// ── 5. Fetch Dishes ────────────────────────────────────────────────────────
$dataSql = "SELECT d.dish_id, d.name, d.description, d.price, d.image_path, d.is_active, d.created_at,
                   c.name AS category_name,
                   COALESCE(i.qty_available, 0) AS qty_available,
                   COALESCE(i.reorder_level, 5) AS reorder_level
            FROM dishes d
            JOIN categories c ON d.category_id = c.category_id
            LEFT JOIN inventory i ON d.dish_id = i.dish_id
            {$whereClause}
            ORDER BY d.dish_id DESC
            LIMIT :offset, :per_page";

$stmt = $db->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$stmt->execute();
$dishes = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="container" style="padding:var(--sp-8) 0 var(--sp-16);">

  <!-- Page Header & Action Buttons -->
  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--sp-4);margin-bottom:var(--sp-8);">
    <div>
      <div style="font-size:.8125rem;color:var(--clr-primary);font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
        Kitchen Staff &bull; Component B
      </div>
      <h1 style="font-size:2rem;margin-top:var(--sp-1);">Dishes & Catalogue Management</h1>
    </div>

    <div class="d-flex gap-3" style="flex-wrap:wrap;">
      <a href="<?= app_url('/menu/dish-form.php') ?>" class="btn btn-primary">
        ➕ Add New Dish
      </a>
      <a href="<?= app_url('/menu/categories.php') ?>" class="btn btn-secondary">
        📁 Manage Categories
      </a>
      <a href="<?= app_url('/menu/stock.php') ?>" class="btn btn-ghost">
        📊 Stock Dashboard
      </a>
    </div>
  </div>

  <!-- KPI Summary Badges -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:var(--sp-4);margin-bottom:var(--sp-8);">
    <div class="card" style="padding:var(--sp-4) var(--sp-5);">
      <div style="font-size:.8125rem;color:var(--clr-text-muted);">Total Dishes</div>
      <div style="font-size:1.75rem;font-weight:800;font-family:var(--font-display);"><?= (int)$stats['total'] ?></div>
    </div>
    <div class="card" style="padding:var(--sp-4) var(--sp-5);">
      <div style="font-size:.8125rem;color:var(--clr-text-muted);">Active Menu Items</div>
      <div style="font-size:1.75rem;font-weight:800;font-family:var(--font-display);color:var(--clr-success);"><?= (int)$stats['active_count'] ?></div>
    </div>
    <div class="card" style="padding:var(--sp-4) var(--sp-5);">
      <div style="font-size:.8125rem;color:var(--clr-text-muted);">Soft-Deleted / Archived</div>
      <div style="font-size:1.75rem;font-weight:800;font-family:var(--font-display);color:var(--clr-text-muted);"><?= (int)$stats['inactive_count'] ?></div>
    </div>
    <div class="card" style="padding:var(--sp-4) var(--sp-5);">
      <div style="font-size:.8125rem;color:var(--clr-text-muted);">Out of Stock (Active)</div>
      <div style="font-size:1.75rem;font-weight:800;font-family:var(--font-display);color:var(--clr-error);"><?= (int)$stats['out_of_stock'] ?></div>
    </div>
  </div>

  <!-- Search & Filter Controls -->
  <div class="card" style="padding:var(--sp-4) var(--sp-5);margin-bottom:var(--sp-6);">
    <form method="GET" action="<?= app_url('/menu/manage.php') ?>" class="d-flex gap-3 align-center" style="flex-wrap:wrap;">
      
      <!-- Keyword Search -->
      <div style="flex:1 1 220px;">
        <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search dish name..." style="width:100%;">
      </div>

      <!-- Category Filter -->
      <div style="flex:0 0 200px;">
        <select name="cat" onchange="this.form.submit()" style="width:100%;">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= $selectedCat === (int)$c['category_id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status Filter -->
      <div style="flex:0 0 160px;">
        <select name="status" onchange="this.form.submit()" style="width:100%;">
          <option value="all"      <?= $statusFilter === 'all'      ? 'selected' : '' ?>>All Statuses</option>
          <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>🟢 Active Only</option>
          <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>🔴 Inactive (Archived)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>

      <?php if ($searchQuery !== '' || $selectedCat !== null || $statusFilter !== 'all'): ?>
        <a href="<?= app_url('/menu/manage.php') ?>" class="btn btn-ghost btn-sm">✕ Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Dishes Management Table -->
  <div class="table-container card" style="padding:0;overflow:hidden;">
    <table class="table" style="width:100%;margin:0;">
      <thead>
        <tr>
          <th style="width:70px;">Image</th>
          <th>Dish Name</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock Status</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($dishes)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:var(--sp-10);color:var(--clr-text-muted);">
              No dishes found matching your criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($dishes as $dish): ?>
            <?php 
              $qty = (int)$dish['qty_available'];
              $reorder = (int)$dish['reorder_level'];
              $imgUrl = !empty($dish['image_path']) ? app_url('/' . ltrim($dish['image_path'], '/')) : app_url('/uploads/dishes/biriyani.jpg');
            ?>
            <tr style="<?= !$dish['is_active'] ? 'opacity:.65;background:rgba(0,0,0,.2);' : '' ?>">
              <!-- Image Thumbnail -->
              <td>
                <img src="<?= e($imgUrl) ?>" 
                     alt="<?= e($dish['name']) ?>" 
                     style="width:52px;height:52px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--clr-border);">
              </td>

              <!-- Dish Info -->
              <td>
                <div style="font-weight:700;font-size:.95rem;color:var(--clr-text);">
                  <?= e($dish['name']) ?>
                </div>
                <div style="font-size:.8rem;color:var(--clr-text-muted);max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= e($dish['description'] ?: 'No description provided.') ?>
                </div>
              </td>

              <!-- Category -->
              <td>
                <span class="badge" style="background:var(--clr-surface);color:var(--clr-text-muted);border:1px solid var(--clr-border);">
                  <?= e($dish['category_name']) ?>
                </span>
              </td>

              <!-- Price -->
              <td style="font-weight:700;font-family:var(--font-display);">
                <?= format_lkr((float)$dish['price']) ?>
              </td>

              <!-- Stock & Badge -->
              <td>
                <div style="display:flex;flex-direction:column;gap:4px;">
                  <?= render_stock_badge($qty, $reorder, true) ?>
                  <div style="font-size:.75rem;color:var(--clr-text-subtle);">
                    Reorder at: <?= $reorder ?>
                  </div>
                </div>
              </td>

              <!-- Active / Soft-Deleted Status -->
              <td>
                <?php if ($dish['is_active']): ?>
                  <span class="badge badge-success">Active</span>
                <?php else: ?>
                  <span class="badge badge-error" title="Soft-deleted: hidden from public menu but preserves order history">Archived</span>
                <?php endif; ?>
              </td>

              <!-- Actions -->
              <td style="text-align:right;">
                <div class="d-flex gap-2" style="justify-content:flex-end;align-items:center;">
                  <!-- View public detail -->
                  <a href="<?= app_url('/menu/detail.php?id=' . (int)$dish['dish_id']) ?>" 
                     class="btn btn-ghost btn-sm" 
                     target="_blank"
                     title="View in public menu">
                    👁️
                  </a>

                  <!-- Edit Dish -->
                  <a href="<?= app_url('/menu/dish-form.php?id=' . (int)$dish['dish_id']) ?>" 
                     class="btn btn-secondary btn-sm" 
                     title="Edit dish and price">
                    ✏️ Edit
                  </a>

                  <!-- Soft Delete / Restore Form -->
                  <form method="POST" action="<?= app_url('/menu/dish-delete.php') ?>" style="margin:0;" 
                        onsubmit="return confirm('<?= $dish['is_active'] ? 'Archive (soft delete) this dish? It will be hidden from public view, but past customer orders will remain intact.' : 'Restore this dish to the active public menu?' ?>');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="dish_id" value="<?= (int)$dish['dish_id'] ?>">
                    <input type="hidden" name="action" value="<?= $dish['is_active'] ? 'soft_delete' : 'restore' ?>">
                    
                    <?php if ($dish['is_active']): ?>
                      <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--clr-error);" title="Soft delete dish">
                        🗑️
                      </button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-sm btn-primary" title="Restore dish">
                        ♻️ Restore
                      </button>
                    <?php endif; ?>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pagination['last_page'] > 1): ?>
    <div style="margin-top:var(--sp-6);">
      <?php 
        $queryArgs = [
          'cat'    => $selectedCat,
          'q'      => $searchQuery !== '' ? $searchQuery : null,
          'status' => $statusFilter !== 'all' ? $statusFilter : null,
        ];
      ?>
      <nav class="pagination" aria-label="Dishes management pagination">
        <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
          <?php 
            $queryArgs['page'] = $p;
            $link = '?' . http_build_query(array_filter($queryArgs, fn($v) => $v !== null));
          ?>
          <a href="<?= $link ?>" class="page-link <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </nav>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
