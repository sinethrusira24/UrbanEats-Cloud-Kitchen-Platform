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

    <!-- Menu Catalogue Header -->
    <div class="menu-catalogue-header">
        <!-- Decorative Background Watermark Accents -->
        <svg class="header-bg-accent header-bg-accent-left" width="160" height="160" viewBox="0 0 160 160" fill="none" aria-hidden="true">
            <circle cx="80" cy="80" r="68" stroke="currentColor" stroke-width="1.2" stroke-dasharray="4 6"/>
            <circle cx="80" cy="80" r="42" stroke="currentColor" stroke-width="1"/>
            <circle cx="80" cy="80" r="16" stroke="currentColor" stroke-width="0.8"/>
            <path d="M80 6v148M6 80h148" stroke="currentColor" stroke-width="0.8" stroke-dasharray="2 4"/>
        </svg>
        <svg class="header-bg-accent header-bg-accent-right" width="160" height="160" viewBox="0 0 160 160" fill="none" aria-hidden="true">
            <path d="M50 102c0-26 13.4-42 30-42s30 16 30 42H50z" stroke="currentColor" stroke-width="1.3"/>
            <line x1="42" y1="108" x2="118" y2="108" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <circle cx="80" cy="54" r="4" stroke="currentColor" stroke-width="1.2"/>
            <path d="M125 45l3 8 8 3-8 3-3 8-3-8-8-3 8-3 3-8z" fill="currentColor"/>
            <path d="M35 120l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5z" fill="currentColor"/>
        </svg>

        <div class="header-content-inner">
            <div class="header-live-badge">
                <span class="pulse-indicator">
                    <span class="pulse-ring"></span>
                    <span class="pulse-dot"></span>
                </span>
                Freshly Prepared Everyday
            </div>
            <h1 class="header-title">Cloud Kitchen <span class="text-gradient">Catalogue</span></h1>
            <p class="header-desc">
                Explore our curated culinary creations crafted fresh in our Colombo cloud kitchens. Fast delivery guaranteed.
            </p>
            <div class="header-highlights">
                <span class="highlight-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    30–45m Express Delivery
                </span>
                <span class="highlight-divider">•</span>
                <span class="highlight-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    100% Fresh & Authentic
                </span>
                <span class="highlight-divider">•</span>
                <span class="highlight-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path><line x1="6" y1="17" x2="18" y2="17"></line></svg>
                    Chef Handcrafted
                </span>
            </div>
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
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
