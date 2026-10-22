<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu, Catalogue and Inventory
 *
 * Real-Time Inventory & Stock Dashboard
 *
 * Requirements:
 * - Protected by RBAC: require_role(['kitchen_staff', 'admin'])
 * - Dynamic KPI summary cards (Total, In Stock, Low Stock, Out of Stock)
 * - Visual inventory progress meters with color-coded alerts
 * - Restock & Wastage adjustment modals with mandatory reason logging
 * - Concurrency protection preventing negative inventory
 * - Live audit trail of recent stock movements joined with Component A users
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/stock.php';
require_once dirname(__DIR__, 2) . '/includes/component_b.php';

require_role(['kitchen_staff', 'admin']);

$pdo = get_db_connection();
$userId = current_user_id();

// ── Handle Stock Adjustments (POST) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $dishId       = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    $movementType = trim((string)($_POST['movement_type'] ?? 'Restock'));
    $quantity     = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $reason       = trim((string)($_POST['reason'] ?? ''));

    if (!$dishId || $dishId <= 0) {
        flash('danger', 'Invalid dish specified for stock adjustment.');
        redirect('menu/stock.php');
    }

    if (!$quantity || $quantity <= 0) {
        flash('danger', 'Adjustment quantity must be at least 1 portion.');
        redirect('menu/stock.php');
    }

    if ($reason === '') {
        flash('danger', 'A reason is mandatory for manual stock adjustments.');
        redirect('menu/stock.php');
    }

    $changeQty = ($movementType === 'Wastage') ? -$quantity : $quantity;

    try {
        adjust_stock($dishId, $changeQty, $movementType, $reason, $userId, $pdo);

        log_action(
            'stock_adjusted',
            sprintf('Adjusted stock for dish #%d: %s (%+d portions). Reason: %s', $dishId, $movementType, $changeQty, $reason),
            $userId,
            $pdo
        );

        flash('success', sprintf('Successfully recorded %s of %d portion(s).', strtolower($movementType), $quantity));
    } catch (InsufficientStockException $e) {
        flash('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Stock adjustment error: ' . $e->getMessage());
        flash('danger', 'An unexpected error occurred while processing stock adjustment.');
    }

    redirect('menu/stock.php');
}

// ── 1. Calculate Summary KPIs ──────────────────────────────────────────────
$kpis = get_stock_summary($pdo);

// ── 2. Filters & Search for Inventory Items ────────────────────────────────
$searchQuery = trim((string)($_GET['q'] ?? ''));
$stockStatus = trim((string)($_GET['status'] ?? 'all')); // 'all', 'low', 'out', 'in'

$whereClauses = ['d.is_active = 1'];
$queryParams  = [];

if ($searchQuery !== '') {
    $whereClauses[] = '(d.name LIKE :search_name OR c.name LIKE :search_cat)';
    $queryParams[':search_name'] = '%' . $searchQuery . '%';
    $queryParams[':search_cat']  = '%' . $searchQuery . '%';
}

if ($stockStatus === 'low') {
    $whereClauses[] = 'COALESCE(i.qty_available, 0) > 0 AND COALESCE(i.qty_available, 0) <= COALESCE(i.reorder_level, 5)';
} elseif ($stockStatus === 'out') {
    $whereClauses[] = 'COALESCE(i.qty_available, 0) <= 0';
} elseif ($stockStatus === 'in') {
    $whereClauses[] = 'COALESCE(i.qty_available, 0) > COALESCE(i.reorder_level, 5)';
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch items
$itemsSql = "SELECT d.dish_id, d.name AS dish_name, d.price, d.image_path,
                    c.name AS category_name,
                    COALESCE(i.qty_available, 0) AS qty_available,
                    COALESCE(i.reorder_level, 5) AS reorder_level,
                    i.updated_at
             FROM dishes d
             JOIN categories c ON d.category_id = c.category_id
             LEFT JOIN inventory i ON d.dish_id = i.dish_id
             WHERE {$whereSql}
             ORDER BY i.qty_available ASC, d.name ASC";

$stmtItems = $pdo->prepare($itemsSql);
$stmtItems->execute($queryParams);
$inventoryItems = $stmtItems->fetchAll();

// ── 3. Fetch Recent Stock Movement History ─────────────────────────────────
$movementsSql = "SELECT sm.movement_id, sm.dish_id, sm.movement_type, sm.change_qty,
                        sm.previous_stock, sm.updated_stock, sm.reason, sm.moved_at,
                        d.name AS dish_name,
                        COALESCE(u.full_name, 'System / Customer') AS staff_name
                 FROM stock_movements sm
                 JOIN dishes d ON sm.dish_id = d.dish_id
                 LEFT JOIN users u ON sm.user_id = u.user_id
                 ORDER BY sm.movement_id DESC
                 LIMIT 15";
$recentMovements = $pdo->query($movementsSql)->fetchAll();

$pageTitle = 'Stock Dashboard';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="padding: 1rem 0 3.5rem;">

    <!-- Dashboard Title Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-kitchen_staff" style="font-size: 0.75rem; text-transform: uppercase;">Inventory Control</span>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0.25rem 0 0; color: var(--text-main);">Kitchen Stock Dashboard</h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.9375rem;">Monitor real-time portion availability, record batch restocking, and track kitchen wastage.</p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= base_url('menu/manage.php') ?>" class="btn btn-outline">
                🍽️ Manage Dishes
            </a>
            <a href="<?= base_url('menu/categories.php') ?>" class="btn btn-outline">
                🏷️ Categories
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div>
                <div class="kpi-val"><?= $kpis['total_dishes'] ?></div>
                <div class="kpi-label">Tracked Dishes</div>
            </div>
            <div class="kpi-icon" style="background: var(--primary-light); color: var(--primary);">🍲</div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-val" style="color: var(--success);"><?= $kpis['in_stock'] ?></div>
                <div class="kpi-label">Sufficient Stock</div>
            </div>
            <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">✅</div>
        </div>

        <div class="kpi-card" style="<?= $kpis['low_stock'] > 0 ? 'border-color: var(--warning-border); background: #fffbeb;' : '' ?>">
            <div>
                <div class="kpi-val" style="color: var(--warning-text);"><?= $kpis['low_stock'] ?></div>
                <div class="kpi-label">Low Stock Warnings</div>
            </div>
            <div class="kpi-icon" style="background: #fef3c7; color: #d97706;">⚠️</div>
        </div>

        <div class="kpi-card" style="<?= $kpis['out_of_stock'] > 0 ? 'border-color: var(--danger-border); background: #fef2f2;' : '' ?>">
            <div>
                <div class="kpi-val" style="color: var(--danger-text);"><?= $kpis['out_of_stock'] ?></div>
                <div class="kpi-label">Sold Out / Depleted</div>
            </div>
            <div class="kpi-icon" style="background: #fee2e2; color: #dc2626;">❌</div>
        </div>
    </div>

    <!-- Inventory Search & Filter Bar -->
    <form method="GET" action="<?= base_url('menu/stock.php') ?>" class="menu-filter-bar">
        <div class="filter-search-wrap">
            <span class="filter-search-icon">🔍</span>
            <input type="text"
                   name="q"
                   value="<?= e($searchQuery) ?>"
                   placeholder="Search dishes by name or category..."
                   class="filter-search-input"
                   aria-label="Search stock items">
        </div>

        <div class="filter-controls">
            <select name="status" class="filter-select" onchange="this.form.submit()" aria-label="Filter by stock status">
                <option value="all" <?= $stockStatus === 'all' ? 'selected' : '' ?>>All Inventory</option>
                <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Low Stock Only (≤ Reorder)</option>
                <option value="out" <?= $stockStatus === 'out' ? 'selected' : '' ?>>Out of Stock Only</option>
                <option value="in" <?= $stockStatus === 'in' ? 'selected' : '' ?>>In Stock Only</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filter</button>

            <?php if ($searchQuery !== '' || $stockStatus !== 'all'): ?>
                <a href="<?= base_url('menu/stock.php') ?>" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Real-Time Stock Table -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 3rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--text-main);">Current Portion Balances (<?= count($inventoryItems) ?> items)</h2>
            <span style="font-size: 0.8125rem; color: var(--text-muted);">Atomic update protection enabled</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dish Offering</th>
                        <th>Category</th>
                        <th style="width: 140px;">Portions Ready</th>
                        <th style="width: 160px;">Reorder Level</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 220px;">Manual Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventoryItems)): ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 3rem; color: var(--text-muted);">
                                No inventory records matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventoryItems as $item): ?>
                            <?php
                            $qty = (int)$item['qty_available'];
                            $reorder = (int)$item['reorder_level'];
                            $isOut = ($qty <= 0);
                            $isLow = ($qty > 0 && $qty <= $reorder);
                            $thumb = !empty($item['image_path']) ? base_url($item['image_path']) : base_url('images/hero_dish.jpg');
                            ?>
                            <tr style="<?= $isOut ? 'background: #fef2f2;' : ($isLow ? 'background: #fffbeb;' : '') ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <img src="<?= e($thumb) ?>"
                                             alt="<?= e($item['dish_name']) ?>"
                                             style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color);"
                                             onerror="this.onerror=null; this.src='<?= base_url('images/hero_dish.jpg') ?>';">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-main); font-size: 0.9375rem;">
                                                <a href="<?= base_url('menu/detail.php?id=' . (int)$item['dish_id']) ?>" style="color: inherit; text-decoration: none;">
                                                    <?= e($item['dish_name']) ?>
                                                </a>
                                            </div>
                                            <div style="font-size: 0.8125rem; color: var(--primary); font-weight: 600;">
                                                <?= format_lkr((float)$item['price']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size: 0.75rem;">
                                        <?= e($item['category_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 1.125rem; font-weight: 800; font-feature-settings: 'tnum'; color: <?= $isOut ? 'var(--danger)' : ($isLow ? 'var(--warning-text)' : 'var(--text-main)') ?>;">
                                        <?= $qty ?>
                                    </div>
                                    <div class="stock-bar-wrap" style="width: 100px; height: 6px;">
                                        <?php
                                        $pct = min(100, (int)(($qty / max(1, $reorder * 4)) * 100));
                                        $cls = $isOut ? 'low' : ($isLow ? 'medium' : 'high');
                                        ?>
                                        <div class="stock-bar-fill <?= $cls ?>" style="width: <?= max(0, $pct) ?>%;"></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                        Buffer: <strong><?= $reorder ?></strong> portions
                                    </div>
                                </td>
                                <td>
                                    <?= render_stock_badge($qty, $reorder, false) ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.4rem;">
                                        <!-- Restock Trigger Button -->
                                        <button type="button"
                                                class="btn btn-primary btn-sm"
                                                onclick="openAdjustmentModal(<?= (int)$item['dish_id'] ?>, '<?= e(addslashes($item['dish_name'])) ?>', 'Restock', <?= $qty ?>)">
                                            ➕ Restock
                                        </button>

                                        <!-- Wastage Trigger Button -->
                                        <button type="button"
                                                class="btn btn-outline btn-sm"
                                                style="color: var(--danger); border-color: var(--danger-border);"
                                                onclick="openAdjustmentModal(<?= (int)$item['dish_id'] ?>, '<?= e(addslashes($item['dish_name'])) ?>', 'Wastage', <?= $qty ?>)">
                                            🗑️ Wastage
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stock Movement History Section (Audit Trail) -->
    <div class="card" style="padding: 0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--text-main);">Recent Stock Movement History (Audit Trail)</h2>
            <span style="font-size: 0.8125rem; color: var(--text-muted);">Last 15 transactional entries</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Dish</th>
                        <th>Movement Type</th>
                        <th>Adjustment</th>
                        <th>Stock Balance</th>
                        <th>Responsible Staff</th>
                        <th>Reason / Notes</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 2.5rem; color: var(--text-muted);">
                                No stock movements recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $m): ?>
                            <?php
                            $change = (int)$m['change_qty'];
                            $isNegative = ($change < 0);
                            $typeBadgeClass = match ($m['movement_type']) {
                                'Restock'            => 'badge-success',
                                'Wastage'            => 'badge-danger',
                                'Checkout_Deduction' => 'badge-info',
                                default              => 'badge-warning',
                            };
                            ?>
                            <tr>
                                <td style="font-family: monospace; color: var(--text-dim);">#<?= (int)$m['movement_id'] ?></td>
                                <td style="font-weight: 600; color: var(--text-main);"><?= e($m['dish_name']) ?></td>
                                <td>
                                    <span class="badge <?= $typeBadgeClass ?>" style="font-size: 0.75rem;">
                                        <?= e(str_replace('_', ' ', $m['movement_type'])) ?>
                                    </span>
                                </td>
                                <td style="font-weight: 700; font-feature-settings: 'tnum'; color: <?= $isNegative ? 'var(--danger)' : 'var(--success)' ?>;">
                                    <?= ($change > 0 ? '+' : '') . $change ?>
                                </td>
                                <td style="font-size: 0.8125rem; color: var(--text-muted); font-feature-settings: 'tnum';">
                                    <?= (int)$m['previous_stock'] ?> &rarr; <strong style="color: var(--text-main);"><?= (int)$m['updated_stock'] ?></strong>
                                </td>
                                <td style="font-size: 0.875rem;">
                                    <span>👤 <?= e($m['staff_name']) ?></span>
                                </td>
                                <td style="font-size: 0.8125rem; color: var(--text-muted); max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($m['reason'] ?? '') ?>">
                                    <?= e($m['reason'] ?? 'Standard adjustment') ?>
                                </td>
                                <td style="font-size: 0.8125rem; color: var(--text-dim); white-space: nowrap;">
                                    <?= date('M d, H:i', strtotime($m['moved_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Stock Adjustment Modal -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Stock Adjustment</h3>
            <button type="button" class="modal-close-btn" onclick="closeAdjustmentModal()">&times;</button>
        </div>

        <form method="POST" action="<?= base_url('menu/stock.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="dish_id" id="modalDishId">
            <input type="hidden" name="movement_type" id="modalMovementType">

            <div class="modal-body">
                <div style="background: var(--bg-subtle); padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); font-weight: 600;">Selected Dish</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: var(--text-main);" id="modalDishName"></div>
                    <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.2rem;" id="modalCurrentStock"></div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="adj_qty" class="form-label" id="modalQtyLabel">Portions to Add <span style="color: var(--danger);">*</span></label>
                    <input type="number"
                           name="quantity"
                           id="adj_qty"
                           class="form-control"
                           min="1"
                           value="10"
                           required>
                    <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.25rem;" id="modalQtyHelp"></div>
                </div>

                <div class="form-group">
                    <label for="adj_reason" class="form-label">Mandatory Operational Reason <span style="color: var(--danger);">*</span></label>
                    <input type="text"
                           name="reason"
                           id="adj_reason"
                           class="form-control"
                           placeholder="e.g. Fresh prep batch from morning shift"
                           required>
                    <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.25rem;">Recorded in audit log under your kitchen staff account.</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAdjustmentModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjustmentModal(dishId, dishName, type, currentQty) {
    document.getElementById('modalDishId').value = dishId;
    document.getElementById('modalMovementType').value = type;
    document.getElementById('modalDishName').textContent = dishName;
    document.getElementById('modalCurrentStock').textContent = 'Current Available Portions: ' + currentQty;

    const title = document.getElementById('modalTitle');
    const qtyLabel = document.getElementById('modalQtyLabel');
    const qtyHelp = document.getElementById('modalQtyHelp');
    const submitBtn = document.getElementById('modalSubmitBtn');
    const reasonInput = document.getElementById('adj_reason');
    const qtyInput = document.getElementById('adj_qty');

    if (type === 'Wastage') {
        title.textContent = 'Record Kitchen Wastage';
        qtyLabel.innerHTML = 'Portions to Deduct (Wastage) <span style="color: var(--danger);">*</span>';
        qtyHelp.textContent = 'Cannot exceed current stock balance (' + currentQty + ').';
        qtyInput.max = currentQty;
        qtyInput.value = Math.min(currentQty, 5);
        reasonInput.placeholder = 'e.g. Spoiled ingredients, dropped tray, or expired shift prep';
        submitBtn.textContent = 'Record Wastage';
        submitBtn.className = 'btn btn-outline';
        submitBtn.style.color = 'var(--danger)';
        submitBtn.style.borderColor = 'var(--danger)';
    } else {
        title.textContent = 'Restock Kitchen Portions';
        qtyLabel.innerHTML = 'Portions to Add <span style="color: var(--danger);">*</span>';
        qtyHelp.textContent = 'Added immediately to live portions available.';
        qtyInput.removeAttribute('max');
        qtyInput.value = 15;
        reasonInput.placeholder = 'e.g. Fresh kitchen prep batch ready to serve';
        submitBtn.textContent = 'Confirm Restock';
        submitBtn.className = 'btn btn-primary';
        submitBtn.style.color = '';
        submitBtn.style.borderColor = '';
    }

    document.getElementById('adjustModal').classList.add('open');
}

function closeAdjustmentModal() {
    document.getElementById('adjustModal').classList.remove('open');
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('adjustModal');
    if (e.target === modal) {
        closeAdjustmentModal();
    }
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
