<?php
/**
 * stock.php – Component B: Kitchen Stock Dashboard & Inventory Control.
 *
 * Restricted to Kitchen Staff & Administrator.
 * Features:
 *   - KPI summary cards (Total, Healthy, Low Stock, Out of Stock)
 *   - Filterable inventory table highlighting low-stock and out-of-stock items
 *   - Visual stock meters
 *   - Transaction-safe Restock and Wastage adjustment modals
 *   - Reorder level updater
 *   - Full stock movement audit trail showing who made each change and why
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/stock.php';

require_role(['Kitchen Staff', 'Administrator']);

$pageTitle = 'Stock Dashboard & Inventory';
$db = get_db();

// ── Handle Stock Adjustment POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);

    if (!$dishId) {
        flash('error', 'Invalid dish identifier.');
        redirect(app_url('/menu/stock.php'));
    }

    if ($action === 'adjust_stock') {
        $type   = $_POST['adj_type'] ?? 'restock'; // 'restock' | 'wastage'
        $qtyRaw = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $reasonPreset = sanitise_string($_POST['reason_preset'] ?? '');
        $notes  = sanitise_string($_POST['notes'] ?? '');

        $reason = $reasonPreset;
        if ($notes !== '') {
            $reason .= ($reason !== '' ? ' – ' : '') . $notes;
        }

        if ($qtyRaw === false || $qtyRaw <= 0) {
            flash('error', 'Adjustment quantity must be a positive number.');
        } elseif ($reason === '') {
            flash('error', 'Please provide a reason for the stock adjustment.');
        } else {
            $changeQty = ($type === 'wastage') ? -$qtyRaw : $qtyRaw;
            try {
                adjust_stock($dishId, $changeQty, $reason, $_SESSION['user_id'] ?? null);
                $dishName = $db->query("SELECT name FROM dishes WHERE dish_id = {$dishId}")->fetchColumn();
                $typeLabel = ($type === 'restock') ? 'Restocked +' . $qtyRaw : 'Recorded wastage -' . $qtyRaw;
                flash('success', "{$typeLabel} portions for &ldquo;{$dishName}&rdquo;. Movement recorded.");
            } catch (InsufficientStockException $e) {
                flash('error', $e->getMessage());
            } catch (Throwable $e) {
                error_log('[UrbanEats] Stock adjust error: ' . $e->getMessage());
                flash('error', 'Failed to adjust stock due to an internal error.');
            }
        }
        redirect(app_url('/menu/stock.php'));

    } elseif ($action === 'update_reorder_level') {
        $newLevel = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);
        if ($newLevel === false || $newLevel < 0) {
            flash('error', 'Reorder level must be zero or a positive whole number.');
        } else {
            set_reorder_level($dishId, $newLevel);
            $dishName = $db->query("SELECT name FROM dishes WHERE dish_id = {$dishId}")->fetchColumn();
            flash('success', "Updated low-stock alert threshold to {$newLevel} for &ldquo;{$dishName}&rdquo;.");
        }
        redirect(app_url('/menu/stock.php'));
    }
}

// ── KPI Summary Statistics ─────────────────────────────────────────────────
$summary = get_stock_summary();

// ── Filters & Search ───────────────────────────────────────────────────────
$searchQuery = sanitise_string($_GET['q'] ?? '');
$filterView  = $_GET['view'] ?? 'all'; // all | low | out | healthy

$conditions = ['d.is_active = 1', 'c.is_active = 1'];
$params = [];

if ($searchQuery !== '') {
    $conditions[] = '(d.name LIKE :q OR c.name LIKE :q)';
    $params[':q'] = '%' . $searchQuery . '%';
}

if ($filterView === 'low') {
    $conditions[] = 'i.qty_available > 0 AND i.qty_available <= i.reorder_level';
} elseif ($filterView === 'out') {
    $conditions[] = 'i.qty_available <= 0';
} elseif ($filterView === 'healthy') {
    $conditions[] = 'i.qty_available > i.reorder_level';
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// ── Fetch Filtered Dishes & Inventory ──────────────────────────────────────
$inventorySql = "SELECT d.dish_id, d.name, d.price, d.image_path, c.name AS category_name,
                        COALESCE(i.qty_available, 0) AS qty_available,
                        COALESCE(i.reorder_level, 5) AS reorder_level,
                        i.updated_at AS stock_updated_at
                 FROM dishes d
                 JOIN categories c ON d.category_id = c.category_id
                 LEFT JOIN inventory i ON d.dish_id = i.dish_id
                 {$whereClause}
                 ORDER BY 
                   CASE 
                     WHEN i.qty_available <= 0 THEN 1 
                     WHEN i.qty_available <= i.reorder_level THEN 2 
                     ELSE 3 
                   END,
                   i.qty_available ASC,
                   d.name ASC";

$stmt = $db->prepare($inventorySql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// ── Fetch Recent Stock Movements (Audit Trail) ─────────────────────────────
$movementsSql = "SELECT sm.movement_id, sm.dish_id, sm.change_qty, sm.reason, sm.moved_at,
                        d.name AS dish_name,
                        COALESCE(u.full_name, 'System') AS staff_name
                 FROM stock_movements sm
                 JOIN dishes d ON sm.dish_id = d.dish_id
                 LEFT JOIN users u ON sm.user_id = u.user_id
                 ORDER BY sm.movement_id DESC
                 LIMIT 15";
$recentMovements = $db->query($movementsSql)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="container" style="padding:var(--sp-8) 0 var(--sp-16);">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--sp-4);margin-bottom:var(--sp-8);">
    <div>
      <div style="font-size:.8125rem;color:var(--clr-primary);font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
        Kitchen Operations &bull; Component B
      </div>
      <h1 style="font-size:2rem;margin-top:var(--sp-1);">Stock & Inventory Dashboard</h1>
    </div>

    <div class="d-flex gap-3">
      <a href="<?= app_url('/menu/manage.php') ?>" class="btn btn-secondary">
        🍽️ Dishes Catalogue
      </a>
      <a href="<?= app_url('/menu/categories.php') ?>" class="btn btn-ghost">
        📁 Categories
      </a>
    </div>
  </div>

  <!-- KPI Stat Cards -->
  <div class="stock-kpi-grid">
    <div class="stock-kpi-card kpi-total">
      <div class="kpi-value"><?= $summary['total'] ?></div>
      <div class="kpi-label">Total Active Dishes</div>
    </div>

    <div class="stock-kpi-card kpi-healthy">
      <div class="kpi-value" style="color:var(--clr-success);"><?= $summary['healthy'] ?></div>
      <div class="kpi-label">Well-Stocked Items</div>
    </div>

    <div class="stock-kpi-card kpi-low">
      <div class="kpi-value" style="color:var(--clr-warning);"><?= $summary['low'] ?></div>
      <div class="kpi-label">⚠️ Low Stock Alerts (Needs Restock)</div>
    </div>

    <div class="stock-kpi-card kpi-out">
      <div class="kpi-value" style="color:var(--clr-error);"><?= $summary['out'] ?></div>
      <div class="kpi-label">🔴 Out of Stock (Urgent)</div>
    </div>
  </div>

  <!-- Filter Pills Bar & Search -->
  <div class="card" style="padding:var(--sp-4) var(--sp-5);margin-bottom:var(--sp-6);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--sp-4);">
      
      <!-- Filter Tabs -->
      <div class="d-flex gap-2" style="flex-wrap:wrap;">
        <a href="<?= app_url('/menu/stock.php?view=all' . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>" 
           class="btn btn-sm <?= $filterView === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
          All (<?= $summary['total'] ?>)
        </a>
        <a href="<?= app_url('/menu/stock.php?view=low' . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>" 
           class="btn btn-sm <?= $filterView === 'low' ? 'btn-primary' : 'btn-secondary' ?>"
           style="<?= $summary['low'] > 0 ? 'border-color:var(--clr-warning);' : '' ?>">
          ⚠️ Low Stock (<?= $summary['low'] ?>)
        </a>
        <a href="<?= app_url('/menu/stock.php?view=out' . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>" 
           class="btn btn-sm <?= $filterView === 'out' ? 'btn-primary' : 'btn-secondary' ?>"
           style="<?= $summary['out'] > 0 ? 'border-color:var(--clr-error);' : '' ?>">
          🔴 Out of Stock (<?= $summary['out'] ?>)
        </a>
        <a href="<?= app_url('/menu/stock.php?view=healthy' . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>" 
           class="btn btn-sm <?= $filterView === 'healthy' ? 'btn-primary' : 'btn-secondary' ?>">
          🟢 In Stock (<?= $summary['healthy'] ?>)
        </a>
      </div>

      <!-- Search Input -->
      <form method="GET" action="<?= app_url('/menu/stock.php') ?>" class="d-flex gap-2">
        <input type="hidden" name="view" value="<?= e($filterView) ?>">
        <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search dish..." style="width:200px;">
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <?php if ($searchQuery !== ''): ?>
          <a href="<?= app_url('/menu/stock.php?view=' . urlencode($filterView)) ?>" class="btn btn-ghost btn-sm">✕</a>
        <?php endif; ?>
      </form>

    </div>
  </div>

  <!-- Inventory Control Table -->
  <div class="table-container card" style="padding:0;overflow:hidden;margin-bottom:var(--sp-12);">
    <table class="table" style="width:100%;margin:0;">
      <thead>
        <tr>
          <th style="width:64px;">Item</th>
          <th>Dish Name & Category</th>
          <th>Available Stock</th>
          <th style="min-width:180px;">Stock Level Meter</th>
          <th>Alert Level</th>
          <th>Status</th>
          <th style="text-align:right;">Stock Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:var(--sp-10);color:var(--clr-text-muted);">
              No dishes found matching this view.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <?php 
              $qty = (int)$item['qty_available'];
              $reorder = (int)$item['reorder_level'];
              $isOut = ($qty <= 0);
              $isLow = ($qty > 0 && $qty <= $reorder);
              $healthy = ($qty > $reorder);

              // Percentage for stock meter bar (relative to 3x reorder or minimum 20)
              $meterMax = max(20, $reorder * 3);
              $percent = min(100, max(0, round(($qty / $meterMax) * 100)));
              $fillClass = $isOut ? 'out' : ($isLow ? 'low' : 'healthy');
              $imgUrl = !empty($item['image_path']) ? app_url('/' . ltrim($item['image_path'], '/')) : app_url('/uploads/dishes/biriyani.jpg');
            ?>
            <tr id="dish-row-<?= (int)$item['dish_id'] ?>" 
                style="<?= $isOut ? 'background:rgba(244,63,94,.06);' : ($isLow ? 'background:rgba(251,191,36,.06);' : '') ?>">
              
              <!-- Thumbnail -->
              <td>
                <img src="<?= e($imgUrl) ?>" 
                     alt="<?= e($item['name']) ?>" 
                     style="width:48px;height:48px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--clr-border);">
              </td>

              <!-- Dish & Category -->
              <td>
                <div style="font-weight:700;color:var(--clr-text);">
                  <a href="<?= app_url('/menu/detail.php?id=' . (int)$item['dish_id']) ?>" target="_blank" style="color:inherit;">
                    <?= e($item['name']) ?>
                  </a>
                </div>
                <div style="font-size:.8rem;color:var(--clr-text-muted);">
                  <?= e($item['category_name']) ?> &bull; <?= format_lkr((float)$item['price']) ?>
                </div>
              </td>

              <!-- Current Stock Number -->
              <td>
                <span style="font-size:1.25rem;font-weight:800;font-family:var(--font-display);color:<?= $isOut ? 'var(--clr-error)' : ($isLow ? 'var(--clr-warning)' : 'var(--clr-success)') ?>;">
                  <?= $qty ?>
                </span>
                <span style="font-size:.8rem;color:var(--clr-text-muted);"> portions</span>
              </td>

              <!-- Stock Meter Visual Progress -->
              <td>
                <div class="stock-meter-wrap">
                  <div class="stock-meter-bar" title="Current level: <?= $qty ?> / <?= $meterMax ?> max scale">
                    <div class="stock-meter-fill <?= $fillClass ?>" style="width:<?= $percent ?>%;"></div>
                  </div>
                  <span style="font-size:.75rem;font-weight:600;color:var(--clr-text-muted);min-width:32px;">
                    <?= $percent ?>%
                  </span>
                </div>
              </td>

              <!-- Alert Level -->
              <td>
                <span style="font-size:.9rem;color:var(--clr-text-muted);">
                  &le; <?= $reorder ?> portions
                </span>
              </td>

              <!-- Status Badge -->
              <td>
                <?= render_stock_badge($qty, $reorder, true) ?>
              </td>

              <!-- Actions -->
              <td style="text-align:right;">
                <div class="d-flex gap-2" style="justify-content:flex-end;">
                  <!-- Restock Button -->
                  <button type="button" 
                          class="btn btn-sm btn-primary"
                          onclick="openStockModal(<?= (int)$item['dish_id'] ?>, '<?= e(addslashes($item['name'])) ?>', 'restock', <?= $qty ?>)">
                    ⚡ Restock
                  </button>

                  <!-- Wastage / Deduction Button -->
                  <button type="button" 
                          class="btn btn-sm btn-secondary"
                          onclick="openStockModal(<?= (int)$item['dish_id'] ?>, '<?= e(addslashes($item['name'])) ?>', 'wastage', <?= $qty ?>)">
                    ⚠️ Wastage
                  </button>

                  <!-- Reorder Level Button -->
                  <button type="button" 
                          class="btn btn-sm btn-ghost"
                          title="Change alert threshold"
                          onclick="openReorderModal(<?= (int)$item['dish_id'] ?>, '<?= e(addslashes($item['name'])) ?>', <?= $reorder ?>)">
                    ⚙️
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Stock Movement Audit Log Section -->
  <section>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-4);">
      <div>
        <span style="color:var(--clr-primary);font-size:.8125rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
          Inventory Audit Trail
        </span>
        <h2 style="font-size:1.4rem;margin-top:2px;">Recent Stock Movements</h2>
      </div>
      <span style="font-size:.85rem;color:var(--clr-text-muted);">
        Immutable log recorded for every restock, checkout, and wastage
      </span>
    </div>

    <div class="table-container card" style="padding:0;overflow:hidden;">
      <table class="table" style="width:100%;margin:0;">
        <thead>
          <tr>
            <th>Time</th>
            <th>Dish</th>
            <th>Quantity Change</th>
            <th>Reason / Explanation</th>
            <th>Logged By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentMovements)): ?>
            <tr>
              <td colspan="5" style="text-align:center;padding:var(--sp-8);color:var(--clr-text-muted);">
                No stock movements logged yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentMovements as $m): ?>
              <?php 
                $chg = (int)$m['change_qty'];
                $isPositive = $chg > 0;
              ?>
              <tr>
                <td style="font-size:.85rem;color:var(--clr-text-muted);white-space:nowrap;">
                  <?= date('M d, H:i', strtotime($m['moved_at'])) ?>
                </td>
                <td style="font-weight:600;color:var(--clr-text);">
                  <?= e($m['dish_name']) ?>
                </td>
                <td>
                  <?php if ($isPositive): ?>
                    <span class="badge badge-success" style="font-weight:700;">+<?= $chg ?> portions</span>
                  <?php else: ?>
                    <span class="badge badge-error" style="font-weight:700;"><?= $chg ?> portions</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:.875rem;color:var(--clr-text-muted);">
                  <?= e($m['reason'] ?: 'No explanation specified') ?>
                </td>
                <td style="font-size:.875rem;color:var(--clr-text);">
                  👤 <?= e($m['staff_name']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>

<!-- Stock Adjust Modal Dialog -->
<div class="modal-backdrop" id="stockModal" onclick="if(event.target===this) closeStockModal()">
  <div class="modal-card">
    <div class="modal-header">
      <h2 id="stockModalTitle" style="font-size:1.35rem;margin:0;">⚡ Adjust Stock Level</h2>
      <button type="button" class="modal-close-btn" onclick="closeStockModal()" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?= app_url('/menu/stock.php') ?>" id="stockAdjustForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="adjust_stock">
      <input type="hidden" name="dish_id" id="modalDishId" value="">
      <input type="hidden" name="adj_type" id="modalAdjType" value="restock">

      <!-- Dish Name Banner -->
      <div style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-md);padding:var(--sp-3) var(--sp-4);margin-bottom:var(--sp-4);">
        <div style="font-size:.8rem;color:var(--clr-text-muted);">Target Dish:</div>
        <div id="modalDishName" style="font-weight:700;font-size:1.1rem;color:var(--clr-text);">—</div>
        <div style="font-size:.8rem;color:var(--clr-text-muted);margin-top:2px;">
          Current in kitchen: <strong id="modalCurrentStock" style="color:var(--clr-primary);">0</strong> portions
        </div>
      </div>

      <!-- Quantity -->
      <div class="form-group" style="margin-bottom:var(--sp-4);">
        <label for="adjQtyInput" class="form-label" style="font-weight:600;" id="qtyInputLabel">Portions to Add</label>
        <input type="number" id="adjQtyInput" name="quantity" min="1" required class="form-control" placeholder="e.g. 25">
      </div>

      <!-- Reason Preset Dropdown -->
      <div class="form-group" style="margin-bottom:var(--sp-4);">
        <label for="adjReasonPreset" class="form-label" style="font-weight:600;">Reason Category <span style="color:var(--clr-primary)">*</span></label>
        <select id="adjReasonPreset" name="reason_preset" class="form-control" required>
          <!-- Populated by JS based on restock vs wastage -->
        </select>
      </div>

      <!-- Additional Notes -->
      <div class="form-group" style="margin-bottom:var(--sp-6);">
        <label for="adjNotes" class="form-label" style="font-weight:600;">Staff Notes / Memo</label>
        <input type="text" id="adjNotes" name="notes" class="form-control" placeholder="Optional batch number, supplier note or description...">
      </div>

      <!-- Actions -->
      <div class="d-flex gap-3" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeStockModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="adjSubmitBtn">Record Adjustment</button>
      </div>
    </form>
  </div>
</div>

<!-- Reorder Level Modal Dialog -->
<div class="modal-backdrop" id="reorderModal" onclick="if(event.target===this) closeReorderModal()">
  <div class="modal-card">
    <div class="modal-header">
      <h2 style="font-size:1.35rem;margin:0;">⚙️ Set Alert Threshold</h2>
      <button type="button" class="modal-close-btn" onclick="closeReorderModal()" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?= app_url('/menu/stock.php') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_reorder_level">
      <input type="hidden" name="dish_id" id="reorderDishId" value="">

      <div style="margin-bottom:var(--sp-4);">
        <div style="font-size:.8rem;color:var(--clr-text-muted);">Dish:</div>
        <div id="reorderDishName" style="font-weight:700;font-size:1.1rem;color:var(--clr-text);">—</div>
      </div>

      <div class="form-group" style="margin-bottom:var(--sp-6);">
        <label for="reorderInput" class="form-label" style="font-weight:600;">Low-Stock Alert Level (portions)</label>
        <input type="number" id="reorderInput" name="reorder_level" min="0" required class="form-control">
        <span style="font-size:.75rem;color:var(--clr-text-muted);">
          When kitchen portions drop to or below this threshold, warning badges and staff alerts are triggered.
        </span>
      </div>

      <div class="d-flex gap-3" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeReorderModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Threshold</button>
      </div>
    </form>
  </div>
</div>

<script>
const stockModal = document.getElementById('stockModal');
const modalTitle = document.getElementById('stockModalTitle');
const modalDishId = document.getElementById('modalDishId');
const modalDishName = document.getElementById('modalDishName');
const modalCurrentStock = document.getElementById('modalCurrentStock');
const modalAdjType = document.getElementById('modalAdjType');
const qtyInputLabel = document.getElementById('qtyInputLabel');
const reasonPreset = document.getElementById('adjReasonPreset');
const adjSubmitBtn = document.getElementById('adjSubmitBtn');

const restockReasons = [
  'Supplier Delivery Received',
  'Morning Kitchen Batch Prep',
  'Afternoon Kitchen Batch Prep',
  'Inventory Recount / Reconcile',
  'Special Catering Batch'
];

const wastageReasons = [
  'Kitchen Spoilage / Expired Shelf-Life',
  'Preparation Error / Burned Dish',
  'Customer Return / Spillage',
  'Ingredient Quality Check Rejection',
  'Inventory Recount / Loss Adjustment'
];

function openStockModal(dishId, dishName, type, currentStock) {
  modalDishId.value = dishId;
  modalDishName.textContent = dishName;
  modalCurrentStock.textContent = currentStock;
  modalAdjType.value = type;

  reasonPreset.innerHTML = '';
  const options = (type === 'restock') ? restockReasons : wastageReasons;
  options.forEach(opt => {
    const el = document.createElement('option');
    el.value = opt;
    el.textContent = opt;
    reasonPreset.appendChild(el);
  });

  if (type === 'restock') {
    modalTitle.textContent = '⚡ Restock Portions';
    qtyInputLabel.textContent = 'Portions to Add (+)';
    adjSubmitBtn.textContent = 'Confirm Restock';
    adjSubmitBtn.className = 'btn btn-primary';
  } else {
    modalTitle.textContent = '⚠️ Record Wastage / Usage';
    qtyInputLabel.textContent = 'Portions to Deduct (−)';
    adjSubmitBtn.textContent = 'Record Wastage';
    adjSubmitBtn.className = 'btn btn-secondary';
  }

  document.getElementById('adjQtyInput').value = '';
  document.getElementById('adjNotes').value = '';
  stockModal.classList.add('open');
  document.getElementById('adjQtyInput').focus();
}

function closeStockModal() {
  stockModal.classList.remove('open');
}

const reorderModal = document.getElementById('reorderModal');
const reorderDishId = document.getElementById('reorderDishId');
const reorderDishName = document.getElementById('reorderDishName');
const reorderInput = document.getElementById('reorderInput');

function openReorderModal(dishId, dishName, currentLevel) {
  reorderDishId.value = dishId;
  reorderDishName.textContent = dishName;
  reorderInput.value = currentLevel;
  reorderModal.classList.add('open');
  reorderInput.focus();
}

function closeReorderModal() {
  reorderModal.classList.remove('open');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
