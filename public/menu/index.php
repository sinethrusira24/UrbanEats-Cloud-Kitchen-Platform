<?php
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

    <!-- Sleek Moving Spotlight Banner (Compact Game-Launcher / App-Card Reference Style) -->
    <div class="spotlight-card-banner" id="spotlightBanner" aria-label="Featured Cloud Kitchen Specials">
        <div class="spotlight-slides-track">
            <!-- Slide 0: Chicken Biryani -->
            <div class="spotlight-slide active" data-slide="0">
                <img src="<?= base_url('images/biryani.jpg') ?>" alt="Urban Royal Chicken Biryani" class="spotlight-img">
                <div class="spotlight-bg-overlay"></div>
                <div class="spotlight-content">
                    <div class="spotlight-brand">
                        <span class="spotlight-brand-logo">URBAN<span class="brand-highlight">EATS</span></span>
                        <span class="spotlight-brand-tag">SIGNATURE</span>
                    </div>
                    <span class="spotlight-badge">NEW CHEF CREATION</span>
                    <h2 class="spotlight-title">Urban Royal Chicken Biryani</h2>
                    <div class="spotlight-meta">
                        <span class="spotlight-price">Rs. 1,650.00</span>
                        <span class="spotlight-bullet">•</span>
                        <span class="spotlight-perk">Free Express Delivery</span>
                    </div>
                    <div class="spotlight-actions">
                        <a href="<?= base_url('menu/detail.php?id=1') ?>" class="spotlight-btn-primary">Order Now</a>
                        <button type="button" class="spotlight-btn-icon" data-dish-id="1" aria-label="Bookmark dish" title="Bookmark dish">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Slide 1: Cheese Kottu -->
            <div class="spotlight-slide" data-slide="1">
                <img src="<?= base_url('images/cheese_kottu.jpg') ?>" alt="Spicy Roast Chicken Cheese Kottu" class="spotlight-img">
                <div class="spotlight-bg-overlay"></div>
                <div class="spotlight-content">
                    <div class="spotlight-brand">
                        <span class="spotlight-brand-logo">URBAN<span class="brand-highlight">EATS</span></span>
                        <span class="spotlight-brand-tag">HOT PICKS</span>
                    </div>
                    <span class="spotlight-badge">TOP BESTSELLER</span>
                    <h2 class="spotlight-title">Spicy Roast Chicken Cheese Kottu</h2>
                    <div class="spotlight-meta">
                        <span class="spotlight-price">Rs. 1,450.00</span>
                        <span class="spotlight-bullet">•</span>
                        <span class="spotlight-perk">Sizzling & Melted Cheese</span>
                    </div>
                    <div class="spotlight-actions">
                        <a href="<?= base_url('menu/detail.php?id=2') ?>" class="spotlight-btn-primary">Order Now</a>
                        <button type="button" class="spotlight-btn-icon" data-dish-id="2" aria-label="Bookmark dish" title="Bookmark dish">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Slide 2: Kochchi Chicken Burger -->
            <div class="spotlight-slide" data-slide="2">
                <img src="<?= base_url('images/chicken_burger.jpg') ?>" alt="Crispy Kochchi Chicken Burger" class="spotlight-img">
                <div class="spotlight-bg-overlay"></div>
                <div class="spotlight-content">
                    <div class="spotlight-brand">
                        <span class="spotlight-brand-logo">URBAN<span class="brand-highlight">EATS</span></span>
                        <span class="spotlight-brand-tag">CRUNCH</span>
                    </div>
                    <span class="spotlight-badge">FIERY KOCHCHI SPECIAL</span>
                    <h2 class="spotlight-title">Crispy Kochchi Chicken Burger</h2>
                    <div class="spotlight-meta">
                        <span class="spotlight-price">Rs. 1,250.00</span>
                        <span class="spotlight-bullet">•</span>
                        <span class="spotlight-perk">Freshly Fried & Juicy</span>
                    </div>
                    <div class="spotlight-actions">
                        <a href="<?= base_url('menu/detail.php?id=7') ?>" class="spotlight-btn-primary">Order Now</a>
                        <button type="button" class="spotlight-btn-icon" data-dish-id="7" aria-label="Bookmark dish" title="Bookmark dish">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Slide 3: Teriyaki Chicken Bowl -->
            <div class="spotlight-slide" data-slide="3">
                <img src="<?= base_url('images/hero_dish.jpg') ?>" alt="Artisanal Teriyaki Chicken Bowl" class="spotlight-img">
                <div class="spotlight-bg-overlay"></div>
                <div class="spotlight-content">
                    <div class="spotlight-brand">
                        <span class="spotlight-brand-logo">URBAN<span class="brand-highlight">EATS</span></span>
                        <span class="spotlight-brand-tag">HEALTHY</span>
                    </div>
                    <span class="spotlight-badge">GARDEN FRESH BOWL</span>
                    <h2 class="spotlight-title">Artisanal Teriyaki Chicken Bowl</h2>
                    <div class="spotlight-meta">
                        <span class="spotlight-price">Rs. 1,600.00</span>
                        <span class="spotlight-bullet">•</span>
                        <span class="spotlight-perk">Nutrient Packed & Glazed</span>
                    </div>
                    <div class="spotlight-actions">
                        <a href="<?= base_url('menu/detail.php?id=20') ?>" class="spotlight-btn-primary">Order Now</a>
                        <button type="button" class="spotlight-btn-icon" data-dish-id="20" aria-label="Bookmark dish" title="Bookmark dish">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sleek Carousel Navigation Controls (Floating Bottom-Right Pill) -->
        <div class="spotlight-nav-controls">
            <button type="button" class="spotlight-arrow" id="spotlightPrev" aria-label="Previous Featured Dish">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="spotlight-indicators" id="spotlightIndicators">
                <button type="button" class="spotlight-dash active" data-index="0" aria-label="Dish 1"></button>
                <button type="button" class="spotlight-dash" data-index="1" aria-label="Dish 2"></button>
                <button type="button" class="spotlight-dash" data-index="2" aria-label="Dish 3"></button>
                <button type="button" class="spotlight-dash" data-index="3" aria-label="Dish 4"></button>
            </div>
            <button type="button" class="spotlight-arrow" id="spotlightNext" aria-label="Next Featured Dish">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </div>
    </div>

    <!-- Category Filter Carousel (Clean, modern swipe strip without OS scrollbars or emojis) -->
    <div class="category-carousel-wrapper">
        <button type="button" class="carousel-nav-btn carousel-prev" id="catScrollLeft" aria-label="Scroll categories left">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>

        <div class="category-pills" id="categoryPillsStrip">
            <a href="<?= base_url('menu/index.php?' . http_build_query(array_filter(['q' => $searchQuery, 'sort' => $sortBy]))) ?>"
               class="category-pill <?= $categoryFilter === null ? 'active' : '' ?>">
                <span>All Dishes</span>
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
                    <span><?= e($cat['name']) ?></span>
                    <span class="pill-count"><?= (int)$cat['dish_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <button type="button" class="carousel-nav-btn carousel-next" id="catScrollRight" aria-label="Scroll categories right">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
    </div>

    <!-- Modern Search, Sort & Filter Bar -->
    <form method="GET" action="<?= base_url('menu/index.php') ?>" class="menu-filter-bar" id="menuFilterForm">
        <?php if ($categoryFilter !== null): ?>
            <input type="hidden" name="cat" value="<?= (int)$categoryFilter ?>">
        <?php endif; ?>

        <div class="filter-search-wrap">
            <span class="filter-search-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <input type="text"
                   name="q"
                   id="menuSearchInput"
                   value="<?= e($searchQuery) ?>"
                   placeholder="Search dishes by name or ingredients..."
                   class="filter-search-input"
                   aria-label="Search dishes"
                   autocomplete="off">
            <?php if ($searchQuery !== ''): ?>
                <button type="button" class="filter-search-clear" id="searchClearBtn" title="Clear search" aria-label="Clear search">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            <?php endif; ?>
        </div>

        <div class="filter-controls">
            <div class="filter-select-wrap">
                <svg class="filter-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="6" x2="11" y2="6"></line>
                    <line x1="4" y1="12" x2="16" y2="12"></line>
                    <line x1="4" y1="18" x2="20" y2="18"></line>
                </svg>
                <select name="sort" class="filter-select" onchange="this.form.submit()" aria-label="Sort dishes by">
                    <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Sort: Name (A to Z)</option>
                    <option value="name_desc" <?= $sortBy === 'name_desc' ? 'selected' : '' ?>>Sort: Name (Z to A)</option>
                    <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Sort: Price (Low to High)</option>
                    <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Sort: Price (High to Low)</option>
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

            <?php if ($searchQuery !== '' || $categoryFilter !== null || $sortBy !== 'name_asc'): ?>
                <a href="<?= base_url('menu/index.php') ?>" class="btn-filter-reset" title="Clear all filters">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Active Filter Tags (Quick Remove Pills) -->
    <?php
    $hasActiveFilters = ($searchQuery !== '' || $categoryFilter !== null || $sortBy !== 'name_asc');
    ?>
    <?php if ($hasActiveFilters): ?>
        <div class="active-filter-chips">
            <span class="active-filter-label">Active Filters:</span>

            <?php if ($categoryFilter !== null): ?>
                <?php
                $activeCatObj = null;
                foreach ($activeCategories as $c) {
                    if ((int)$c['category_id'] === $categoryFilter) {
                        $activeCatObj = $c;
                        break;
                    }
                }
                $catRemoveUrl = base_url('menu/index.php?' . http_build_query(array_filter([
                    'q'    => $searchQuery !== '' ? $searchQuery : null,
                    'sort' => $sortBy !== 'name_asc' ? $sortBy : null,
                ])));
                ?>
                <a href="<?= $catRemoveUrl ?>" class="filter-chip" title="Remove category filter">
                    <span>Category: <strong><?= e($activeCatObj['name'] ?? 'Selected') ?></strong></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($searchQuery !== ''): ?>
                <?php
                $searchRemoveUrl = base_url('menu/index.php?' . http_build_query(array_filter([
                    'cat'  => $categoryFilter,
                    'sort' => $sortBy !== 'name_asc' ? $sortBy : null,
                ])));
                ?>
                <a href="<?= $searchRemoveUrl ?>" class="filter-chip" title="Remove search query">
                    <span>Search: "<strong><?= e($searchQuery) ?></strong>"</span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <?php if ($sortBy !== 'name_asc'): ?>
                <?php
                $sortLabels = [
                    'name_desc'  => 'Name: Z to A',
                    'price_asc'  => 'Price: Low to High',
                    'price_desc' => 'Price: High to Low',
                ];
                $sortRemoveUrl = base_url('menu/index.php?' . http_build_query(array_filter([
                    'cat' => $categoryFilter,
                    'q'   => $searchQuery !== '' ? $searchQuery : null,
                ])));
                ?>
                <a href="<?= $sortRemoveUrl ?>" class="filter-chip" title="Reset sorting">
                    <span>Sort: <strong><?= $sortLabels[$sortBy] ?? $sortBy ?></strong></span>
                    <span class="filter-chip-remove">✕</span>
                </a>
            <?php endif; ?>

            <a href="<?= base_url('menu/index.php') ?>" class="filter-clear-all">Clear all</a>
        </div>
    <?php endif; ?>

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
            <div style="margin-bottom: 1rem; color: var(--text-dim);">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                </svg>
            </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Carousel Controls ──────────────────────────────────────────
    const strip = document.getElementById('categoryPillsStrip');
    const btnLeft = document.getElementById('catScrollLeft');
    const btnRight = document.getElementById('catScrollRight');

    if (strip && btnLeft && btnRight) {
        function updateNavState() {
            const maxScroll = strip.scrollWidth - strip.clientWidth;
            btnLeft.style.display = strip.scrollLeft <= 5 ? 'none' : 'flex';
            btnRight.style.display = (strip.scrollLeft >= maxScroll - 5) ? 'none' : 'flex';
        }

        btnLeft.addEventListener('click', function() {
            strip.scrollBy({ left: -260, behavior: 'smooth' });
        });

        btnRight.addEventListener('click', function() {
            strip.scrollBy({ left: 260, behavior: 'smooth' });
        });

        strip.addEventListener('scroll', updateNavState, { passive: true });

        // Auto-center active pill on initial page load
        const activePill = strip.querySelector('.category-pill.active');
        if (activePill) {
            const pillOffset = activePill.offsetLeft - (strip.clientWidth / 2) + (activePill.clientWidth / 2);
            strip.scrollTo({ left: Math.max(0, pillOffset), behavior: 'smooth' });
        }

        // Mouse drag scrolling support
        let isDown = false;
        let startX;
        let scrollLeftPos;

        strip.addEventListener('mousedown', function(e) {
            isDown = true;
            strip.style.cursor = 'grabbing';
            startX = e.pageX - strip.offsetLeft;
            scrollLeftPos = strip.scrollLeft;
        });

        strip.addEventListener('mouseleave', function() {
            isDown = false;
            strip.style.cursor = '';
        });

        strip.addEventListener('mouseup', function() {
            isDown = false;
            strip.style.cursor = '';
        });

        strip.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - strip.offsetLeft;
            const walk = (x - startX) * 1.5;
            strip.scrollLeft = scrollLeftPos - walk;
        });

        updateNavState();
        window.addEventListener('resize', updateNavState);
    }

    // ── Search Clear Button ─────────────────────────────────────────
    const searchInput = document.getElementById('menuSearchInput');
    const clearBtn = document.getElementById('searchClearBtn');
    const filterForm = document.getElementById('menuFilterForm');

    if (clearBtn && searchInput && filterForm) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterForm.submit();
        });
    }

    // ── Sleek Spotlight Banner Controller (Autoplay, Transitions, Swipe, Bookmarking) ──
    const spotlightBanner = document.getElementById('spotlightBanner');
    const spotlightSlides = document.querySelectorAll('.spotlight-slide');
    const spotlightDashes = document.querySelectorAll('.spotlight-dash');
    const spotlightPrevBtn = document.getElementById('spotlightPrev');
    const spotlightNextBtn = document.getElementById('spotlightNext');

    if (spotlightSlides.length > 0) {
        let currentSlide = 0;
        let slideInterval = null;
        const totalSlides = spotlightSlides.length;

        function goToSlide(index) {
            currentSlide = (index + totalSlides) % totalSlides;

            spotlightSlides.forEach((slide, idx) => {
                if (idx === currentSlide) {
                    slide.classList.add('active');
                } else {
                    slide.classList.remove('active');
                }
            });

            spotlightDashes.forEach((dash, idx) => {
                if (idx === currentSlide) {
                    dash.classList.add('active');
                } else {
                    dash.classList.remove('active');
                }
            });
        }

        function nextSlide() {
            goToSlide(currentSlide + 1);
        }

        function prevSlide() {
            goToSlide(currentSlide - 1);
        }

        function startAutoplay() {
            if (!slideInterval) {
                slideInterval = setInterval(nextSlide, 4500);
            }
        }

        function pauseAutoplay() {
            if (slideInterval) {
                clearInterval(slideInterval);
                slideInterval = null;
            }
        }

        if (spotlightNextBtn) {
            spotlightNextBtn.addEventListener('click', () => {
                nextSlide();
                pauseAutoplay();
                startAutoplay();
            });
        }

        if (spotlightPrevBtn) {
            spotlightPrevBtn.addEventListener('click', () => {
                prevSlide();
                pauseAutoplay();
                startAutoplay();
            });
        }

        spotlightDashes.forEach((dash) => {
            dash.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'), 10);
                goToSlide(idx);
                pauseAutoplay();
                startAutoplay();
            });
        });

        // Hover pause
        if (spotlightBanner) {
            spotlightBanner.addEventListener('mouseenter', pauseAutoplay);
            spotlightBanner.addEventListener('mouseleave', startAutoplay);

            // Touch swipe support
            let touchStartX = 0;
            let touchEndX = 0;

            spotlightBanner.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                pauseAutoplay();
            }, { passive: true });

            spotlightBanner.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                if (touchStartX - touchEndX > 45) {
                    nextSlide();
                } else if (touchEndX - touchStartX > 45) {
                    prevSlide();
                }
                startAutoplay();
            }, { passive: true });
        }

        // Bookmark button toggle
        document.querySelectorAll('.spotlight-btn-icon').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.toggle('bookmarked');
                const svg = this.querySelector('svg');
                if (this.classList.contains('bookmarked')) {
                    svg.setAttribute('fill', 'currentColor');
                } else {
                    svg.setAttribute('fill', 'none');
                }
            });
        });

        // Start autoplay
        startAutoplay();
    }
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
