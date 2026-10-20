<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu, Catalogue and Inventory
 *
 * Public Menu Catalogue Page
 *
 * Requirements:
 * - Dynamic category filtering with active counters
 * - Server-side keyword search (dishes & descriptions)
 * - Strict server-side sort whitelist (price_asc, price_desc, name_asc, name_desc)
 * - Concurrency & inventory aware portion availability badges
 * - Full pagination preserving search/sort/filter query strings
 * - Strictly prepared PDO statements for all user inputs
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/stock.php';
require_once dirname(__DIR__, 2) . '/includes/component_b.php';

$pdo = get_db_connection();

// ── 1. Validate & Normalize Query Parameters ───────────────────────────────
$searchQuery  = trim((string)($_GET['q'] ?? ''));
$categoryFilter = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT) ?: null;
$sortBy       = trim((string)($_GET['sort'] ?? 'name_asc'));
$page         = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage      = 9; // 3x3 responsive grid

// ── 2. Whitelist Server-Side Sorting ───────────────────────────────────────
$sortWhitelist = [
    'name_asc'   => 'd.name ASC',
    'name_desc'  => 'd.name DESC',
    'price_asc'  => 'd.price ASC',
    'price_desc' => 'd.price DESC',
];
$orderClause = $sortWhitelist[$sortBy] ?? 'd.name ASC';

// ── 3. Build Dynamic WHERE Conditions ──────────────────────────────────────
$whereClauses = ['d.is_active = 1', 'c.is_active = 1'];
$queryParams  = [];

if ($categoryFilter !== null && $categoryFilter > 0) {
    $whereClauses[] = 'd.category_id = :category_id';
    $queryParams[':category_id'] = $categoryFilter;
}

if ($searchQuery !== '') {
    $whereClauses[] = '(d.name LIKE :search_name OR d.description LIKE :search_desc)';
    $queryParams[':search_name'] = '%' . $searchQuery . '%';
    $queryParams[':search_desc'] = '%' . $searchQuery . '%';
}

$whereSql = implode(' AND ', $whereClauses);

// ── 4. Count Total Matching Records for Pagination ─────────────────────────
$countSql = "SELECT COUNT(d.dish_id) AS total
             FROM dishes d
             JOIN categories c ON d.category_id = c.category_id
             WHERE {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$totalRecords = (int)$stmtCount->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// ── 5. Fetch Paginated Dishes with Real-Time Inventory ─────────────────────
$dishesSql = "SELECT d.dish_id, d.category_id, d.name, d.description, d.price, d.image_path,
                     c.name AS category_name,
                     COALESCE(i.qty_available, 0) AS qty_available,
                     COALESCE(i.reorder_level, 5) AS reorder_level
              FROM dishes d
              JOIN categories c ON d.category_id = c.category_id
              LEFT JOIN inventory i ON d.dish_id = i.dish_id
              WHERE {$whereSql}
              ORDER BY {$orderClause}
              LIMIT :limit OFFSET :offset";

$stmtDishes = $pdo->prepare($dishesSql);
foreach ($queryParams as $paramKey => $paramVal) {
    $stmtDishes->bindValue($paramKey, $paramVal);
}
$stmtDishes->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtDishes->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtDishes->execute();
$dishes = $stmtDishes->fetchAll();

// ── 6. Fetch Active Categories for Filter Pills ────────────────────────────
$activeCategories = get_active_categories($pdo);

// ── 7. Page Metadata & Head Injections ─────────────────────────────────────
$pageTitle = 'Cloud Kitchen Menu';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container" style="padding: 1.5rem 0 3rem;">

    <!-- Menu Catalogue Header -->
    <div style="margin-bottom: 2rem; text-align: center;">
        <span class="badge badge-success" style="font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase;">Freshly Prepared Everyday</span>
        <h1 style="font-size: 2.25rem; font-weight: 800; margin: 0.5rem 0 0.5rem; color: var(--text-main);">Cloud Kitchen Catalogue</h1>
        <p style="color: var(--text-muted); max-width: 620px; margin: 0 auto; font-size: 1rem;">
            Explore our curated culinary creations crafted fresh in our Colombo cloud kitchens. Fast delivery guaranteed.
        </p>
    </div>

    <!-- Category Filter Pills -->
    <div class="category-pills">
        <a href="<?= base_url('menu/index.php?' . http_build_query(array_filter(['q' => $searchQuery, 'sort' => $sortBy]))) ?>"
           class="category-pill <?= $categoryFilter === null ? 'active' : '' ?>">
            <span>🍽️</span> All Dishes
            <span class="pill-count"><?= array_sum(array_column($activeCategories, 'dish_count')) ?></span>
        </a>
        <?php foreach ($activeCategories as $cat): ?>
            <?php
            $catUrlParams = array_filter([
                'cat'  => $cat['category_id'],
                'q'    => $searchQuery !== '' ? $searchQuery : null,
                'sort' => $sortBy !== 'name_asc' ? $sortBy : null,
            ]);
            $isCatActive = ($categoryFilter === (int)$cat['category_id']);
            ?>
            <a href="<?= base_url('menu/index.php?' . http_build_query($catUrlParams)) ?>"
               class="category-pill <?= $isCatActive ? 'active' : '' ?>">
                <?= e($cat['name']) ?>
                <span class="pill-count"><?= (int)$cat['dish_count'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search, Sort & Filter Control Bar -->
    <form method="GET" action="<?= base_url('menu/index.php') ?>" class="menu-filter-bar">
        <?php if ($categoryFilter !== null): ?>
            <input type="hidden" name="cat" value="<?= (int)$categoryFilter ?>">
        <?php endif; ?>

        <div class="filter-search-wrap">
            <span class="filter-search-icon">🔍</span>
            <input type="text"
                   name="q"
                   value="<?= e($searchQuery) ?>"
                   placeholder="Search dishes by name or ingredients..."
                   class="filter-search-input"
                   aria-label="Search dishes">
        </div>

        <div class="filter-controls">
            <select name="sort" class="filter-select" onchange="this.form.submit()" aria-label="Sort dishes by">
                <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Sort: Name (A to Z)</option>
                <option value="name_desc" <?= $sortBy === 'name_desc' ? 'selected' : '' ?>>Sort: Name (Z to A)</option>
                <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Sort: Price (Low to High)</option>
                <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Sort: Price (High to Low)</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filter</button>

            <?php if ($searchQuery !== '' || $categoryFilter !== null || $sortBy !== 'name_asc'): ?>
                <a href="<?= base_url('menu/index.php') ?>" class="btn btn-outline btn-sm" title="Clear all filters">
                    ✕ Reset
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results Count Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
        <div>
            Showing <strong><?= count($dishes) ?></strong> of <strong><?= $totalRecords ?></strong> dishes
            <?php if ($categoryFilter !== null): ?>
                <?php
                $currentCatName = '';
                foreach ($activeCategories as $c) {
                    if ((int)$c['category_id'] === $categoryFilter) {
                        $currentCatName = $c['name'];
                        break;
                    }
                }
                ?>
                in <strong><?= e($currentCatName) ?></strong>
            <?php endif; ?>
            <?php if ($searchQuery !== ''): ?>
                matching "<em><?= e($searchQuery) ?></em>"
            <?php endif; ?>
        </div>
        <div>Page <?= $page ?> of <?= $totalPages ?></div>
    </div>

    <!-- Dish Cards Grid -->
    <?php if (empty($dishes)): ?>
        <div class="card text-center" style="padding: 4rem 2rem; border: 2px dashed var(--border-color); background: #ffffff;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🍲</div>
            <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--text-main);">No Dishes Found</h2>
            <p style="color: var(--text-muted); max-width: 440px; margin: 0 auto 1.5rem;">
                We couldn't find any items matching your active search or category filters. Try checking your spelling or clearing filters.
            </p>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">
                View Entire Menu
            </a>
        </div>
    <?php else: ?>
        <div class="menu-grid">
            <?php foreach ($dishes as $dish): ?>
                <?php
                $qty = (int)$dish['qty_available'];
                $reorder = (int)$dish['reorder_level'];
                $isOutOfStock = ($qty <= 0);
                $imgUrl = !empty($dish['image_path']) ? base_url($dish['image_path']) : base_url('images/hero_dish.jpg');
                ?>
                <article class="dish-card">
                    <div class="dish-card-img-wrap">
                        <a href="<?= base_url('menu/detail.php?id=' . (int)$dish['dish_id']) ?>" tabindex="-1">
                            <img src="<?= e($imgUrl) ?>"
                                 alt="<?= e($dish['name']) ?>"
                                 class="dish-card-img"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='<?= base_url('images/hero_dish.jpg') ?>';">
                        </a>

                        <div class="dish-badge-overlay">
                            <span class="badge badge-info" style="font-size: 0.75rem; background: rgba(2, 132, 199, 0.9); backdrop-filter: blur(4px); color: #fff;">
                                <?= e($dish['category_name']) ?>
                            </span>
                        </div>

                        <div class="dish-stock-overlay">
                            <?= render_stock_badge($qty, $reorder, false) ?>
                        </div>
                    </div>

                    <div class="dish-card-body">
                        <div class="dish-card-category"><?= e($dish['category_name']) ?></div>
                        <h2 class="dish-card-title">
                            <a href="<?= base_url('menu/detail.php?id=' . (int)$dish['dish_id']) ?>">
                                <?= e($dish['name']) ?>
                            </a>
                        </h2>
                        <p class="dish-card-desc"><?= e($dish['description'] ?? 'Freshly prepared specialty dish.') ?></p>

                        <div class="dish-card-footer">
                            <div class="dish-price-tag"><?= format_lkr((float)$dish['price']) ?></div>
                            <div>
                                <a href="<?= base_url('menu/detail.php?id=' . (int)$dish['dish_id']) ?>" class="btn btn-outline btn-sm">
                                    Details
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrap">
                <div style="font-size: 0.875rem; color: var(--text-muted);">
                    Showing page <?= $page ?> of <?= $totalPages ?>
                </div>

                <nav class="pagination-nav" aria-label="Menu pagination">
                    <?php
                    $buildPaginationLink = function (int $targetPage) use ($categoryFilter, $searchQuery, $sortBy): string {
                        $params = [
                            'page' => $targetPage,
                            'sort' => $sortBy,
                        ];
                        if ($categoryFilter !== null) {
                            $params['cat'] = $categoryFilter;
                        }
                        if ($searchQuery !== '') {
                            $params['q'] = $searchQuery;
                        }
                        return base_url('menu/index.php?' . http_build_query($params));
                    };
                    ?>

                    <!-- Prev Page -->
                    <a href="<?= $page > 1 ? $buildPaginationLink($page - 1) : '#' ?>"
                       class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       aria-label="Previous page">
                        &lsaquo; Prev
                    </a>

                    <!-- Numbered Pages -->
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === 1 || $p === $totalPages || abs($p - $page) <= 2): ?>
                            <a href="<?= $buildPaginationLink($p) ?>"
                               class="pagination-btn <?= $p === $page ? 'active' : '' ?>"
                               <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                <?= $p ?>
                            </a>
                        <?php elseif ($p === 2 || $p === $totalPages - 1): ?>
                            <span style="padding: 0 4px; color: var(--text-dim);">&hellip;</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Next Page -->
                    <a href="<?= $page < $totalPages ? $buildPaginationLink($page + 1) : '#' ?>"
                       class="pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
                       aria-label="Next page">
                        Next &rsaquo;
                    </a>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
