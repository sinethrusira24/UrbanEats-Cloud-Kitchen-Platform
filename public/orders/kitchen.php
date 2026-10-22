<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Kitchen Order Management Queue
 *
 * Dedicated operational workflow console for Kitchen Staff and Administrators to view
 * incoming tickets, track fulfillment types, and progress orders through allowed lifecycle stages.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/component_c.php';

// RBAC: Restricted strictly to Kitchen Staff and Administrators
require_role(['kitchen_staff', 'admin']);

$currentUserId = (int)current_user_id();

// Handle Status Advancement / Cancellation POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'advance_status') {
    require_csrf();

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim((string)($_POST['new_status'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($orderId <= 0 || $newStatus === '') {
        flash('danger', 'Invalid status update parameters.');
        redirect('orders/kitchen.php');
    }

    $result = transition_order_status($orderId, $newStatus, $notes, $currentUserId);

    if ($result['success']) {
        flash('success', sprintf('Order #%d transitioned to "%s" successfully.', $orderId, $newStatus));
    } else {
        flash('danger', 'Failed to update order status: ' . $result['error']);
    }

    $filterParam = !empty($_GET['status']) ? '?status=' . urlencode((string)$_GET['status']) : '';
    redirect('orders/kitchen.php' . $filterParam);
}

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$orders = get_kitchen_orders($statusFilter);

// Calculate status counts for filter badges
$allOrdersForCounts = get_kitchen_orders('all');
$statusCounts = [
    'all'              => count($allOrdersForCounts),
    'Placed'           => 0,
    'Preparing'        => 0,
    'Ready'            => 0,
    'Out for Delivery' => 0,
    'Collected'        => 0,
    'Completed'        => 0,
    'Cancelled'        => 0,
];
foreach ($allOrdersForCounts as $o) {
    $st = (string)$o['status'];
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
}

$pageTitle = 'Kitchen Order Queue';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="c-page-header">
    <div>
        <h1 class="c-page-title">
            <span>👨‍🍳</span> Kitchen Order Queue
        </h1>
        <p class="c-page-subtitle">
            Live order fulfillment queue for kitchen chefs and dispatch coordinators.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <span style="font-size: 0.85rem; color: var(--c-muted);">Role: <strong><?= e(current_user_role()) ?></strong></span>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.location.reload();">
            🔄 Refresh Queue
        </button>
    </div>
</div>

<!-- Queue Filter Pills -->
<div class="c-kitchen-filters">
    <a href="<?= base_url('orders/kitchen.php?status=all') ?>" class="c-filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">
        All (<?= $statusCounts['all'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Placed') ?>" class="c-filter-pill <?= $statusFilter === 'Placed' ? 'active' : '' ?>">
        ⏳ Placed (<?= $statusCounts['Placed'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Preparing') ?>" class="c-filter-pill <?= $statusFilter === 'Preparing' ? 'active' : '' ?>">
        🍳 Preparing (<?= $statusCounts['Preparing'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Ready') ?>" class="c-filter-pill <?= $statusFilter === 'Ready' ? 'active' : '' ?>">
        🍱 Ready (<?= $statusCounts['Ready'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Out+for+Delivery') ?>" class="c-filter-pill <?= $statusFilter === 'Out for Delivery' ? 'active' : '' ?>">
        🛵 Out for Delivery (<?= $statusCounts['Out for Delivery'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Collected') ?>" class="c-filter-pill <?= $statusFilter === 'Collected' ? 'active' : '' ?>">
        🛍️ Collected (<?= $statusCounts['Collected'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Completed') ?>" class="c-filter-pill <?= $statusFilter === 'Completed' ? 'active' : '' ?>">
        ✅ Completed (<?= $statusCounts['Completed'] ?>)
    </a>
    <a href="<?= base_url('orders/kitchen.php?status=Cancelled') ?>" class="c-filter-pill <?= $statusFilter === 'Cancelled' ? 'active' : '' ?>">
        ❌ Cancelled (<?= $statusCounts['Cancelled'] ?>)
    </a>
</div>

<?php if (empty($orders)): ?>
    <div class="c-card c-cart-empty-state">
        <div class="c-cart-empty-icon">🍳</div>
        <h2 style="font-size: 1.35rem; color: var(--c-charcoal); margin: 0 0 0.5rem 0;">No active tickets in this view</h2>
        <p style="color: var(--c-muted); max-width: 400px; margin: 0 auto;">
            All pending tickets matching "<?= e($statusFilter) ?>" have been completed or there are no new orders.
        </p>
    </div>
<?php else: ?>

    <div class="c-kitchen-grid">
        <?php foreach ($orders as $ord): ?>
            <?php
            $currentStatus = (string)$ord['status'];
            $fulfillment = (string)$ord['fulfillment_method'];
            $statusClass = 'status-' . strtolower(str_replace(' ', '_', $currentStatus));
            $allowedNext = get_allowed_next_statuses($currentStatus, $fulfillment);
            ?>
            <div class="c-kitchen-card <?= $statusClass ?>">
                <div>
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                        <div>
                            <strong style="font-size: 1.1rem; color: var(--c-charcoal); font-family: monospace;">
                                <?= e($ord['order_number']) ?>
                            </strong>
                            <div style="font-size: 0.82rem; color: var(--c-muted); margin-top: 0.15rem;">
                                <?= date('d M, h:i A', strtotime($ord['created_at'])) ?>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <span class="c-badge c-badge-<?= strtolower(str_replace(' ', '_', $currentStatus)) ?>">
                                <?= e($currentStatus) ?>
                            </span>
                            <div style="font-size: 0.78rem; font-weight: 600; margin-top: 0.3rem;">
                                <?= $fulfillment === 'delivery' ? '🛵 Delivery' : '🛍️ Pickup' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Customer & Payment Summary -->
                    <div style="font-size: 0.88rem; color: var(--c-text); padding: 0.5rem 0; border-top: 1px solid var(--c-border);">
                        <div><strong>Customer:</strong> <?= e($ord['customer_name']) ?> (<?= e($ord['contact_phone']) ?>)</div>
                        <div style="margin-top: 0.2rem;">
                            <strong>Payment:</strong> <?= e($ord['payment_method']) ?>
                            <span class="c-badge c-badge-<?= strtolower((string)$ord['payment_status']) ?>" style="font-size: 0.7rem; padding: 0.1rem 0.4rem;">
                                <?= e($ord['payment_status']) ?>
                            </span>
                        </div>
                        <?php if ($fulfillment === 'delivery' && !empty($ord['delivery_address'])): ?>
                            <div style="margin-top: 0.2rem; color: #4B5563; font-size: 0.82rem;">
                                📍 <?= e($ord['delivery_address']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ordered Dishes Checklist -->
                    <ul class="c-kitchen-items-list">
                        <?php foreach ($ord['items'] as $it): ?>
                            <li>
                                <span>
                                    <strong style="color: var(--c-primary-dark); font-size: 1.05rem;"><?= (int)$it['quantity'] ?> ×</strong>
                                    <?= e($it['dish_name']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($ord['order_notes'])): ?>
                        <div style="background: #FEF3C7; border-left: 3px solid #D97706; padding: 0.5rem 0.75rem; font-size: 0.82rem; color: #92400E; margin-bottom: 0.75rem; border-radius: 4px;">
                            <strong>Note:</strong> <?= e($ord['order_notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Controls -->
                <div style="border-top: 1px solid var(--c-border); padding-top: 0.85rem; margin-top: 0.5rem;">
                    <?php if (!empty($allowedNext)): ?>
                        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            <?php foreach ($allowedNext as $target): ?>
                                <?php if ($target === 'Cancelled'): ?>
                                    <form action="<?= base_url('orders/kitchen.php' . (!empty($_GET['status']) ? '?status=' . urlencode((string)$_GET['status']) : '')) ?>" method="POST" onsubmit="return confirm('Cancel this order and restore stock?');" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="advance_status">
                                        <input type="hidden" name="order_id" value="<?= (int)$ord['order_id'] ?>">
                                        <input type="hidden" name="new_status" value="Cancelled">
                                        <input type="hidden" name="notes" value="Cancelled by kitchen staff">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--c-danger); border-color: #FECACA; font-size: 0.78rem;">
                                            ✕ Cancel Order
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form action="<?= base_url('orders/kitchen.php' . (!empty($_GET['status']) ? '?status=' . urlencode((string)$_GET['status']) : '')) ?>" method="POST" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="advance_status">
                                        <input type="hidden" name="order_id" value="<?= (int)$ord['order_id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= e($target) ?>">
                                        <input type="hidden" name="notes" value="Status advanced to <?= e($target) ?> by staff">
                                        <button type="submit" class="btn btn-primary btn-sm" style="font-size: 0.82rem; font-weight: 600;">
                                            Mark as <?= e($target) ?> →
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.8rem; color: var(--c-muted); text-align: center; padding: 0.35rem 0;">
                            Terminal State (<?= e($currentStatus) ?>)
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.6rem; padding-top: 0.5rem; border-top: 1px dotted var(--c-border); font-size: 0.78rem;">
                        <a href="<?= base_url('orders/track.php?id=' . $ord['order_id']) ?>" target="_blank" style="color: var(--c-muted); text-decoration: none;">
                            🔍 View Timeline
                        </a>
                        <span style="font-weight: 700; color: var(--c-charcoal);">
                            Total: <?= format_lkr($ord['total_amount']) ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
