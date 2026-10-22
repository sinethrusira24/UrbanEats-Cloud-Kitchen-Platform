<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Customer Order History
 *
 * Displays authenticated customer's past and active orders with lifecycle stage filters,
 * pagination, and direct access to order tracking and printable receipts.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/component_c.php';

require_login();

$userId = (int)current_user_id();
$filter = trim((string)($_GET['filter'] ?? 'all'));
$validFilters = ['all', 'active', 'completed', 'cancelled'];
if (!in_array($filter, $validFilters, true)) {
    $filter = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;

$historyData = get_customer_orders($userId, $filter, $page, $perPage);
$orders = $historyData['orders'];
$pagination = $historyData['pagination'];

$pageTitle = 'My Order History';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="c-page-header">
    <div>
        <h1 class="c-page-title">
            <span>📋</span> My Orders
        </h1>
        <p class="c-page-subtitle">Track current meal orders or view receipts and invoices for past deliveries.</p>
    </div>
    <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary btn-sm">
        + Place New Order
    </a>
</div>

<!-- Filter Pills -->
<div class="c-kitchen-filters" style="margin-bottom: 1.5rem;">
    <a href="<?= base_url('orders/history.php?filter=all') ?>" class="c-filter-pill <?= $filter === 'all' ? 'active' : '' ?>">
        All Orders
    </a>
    <a href="<?= base_url('orders/history.php?filter=active') ?>" class="c-filter-pill <?= $filter === 'active' ? 'active' : '' ?>">
        🔥 Active Orders
    </a>
    <a href="<?= base_url('orders/history.php?filter=completed') ?>" class="c-filter-pill <?= $filter === 'completed' ? 'active' : '' ?>">
        ✅ Completed
    </a>
    <a href="<?= base_url('orders/history.php?filter=cancelled') ?>" class="c-filter-pill <?= $filter === 'cancelled' ? 'active' : '' ?>">
        ❌ Cancelled
    </a>
</div>

<?php if (empty($orders)): ?>
    <div class="c-card c-cart-empty-state">
        <div class="c-cart-empty-icon">📦</div>
        <h2 style="font-size: 1.35rem; color: var(--c-charcoal); margin: 0 0 0.5rem 0;">No orders found</h2>
        <p style="color: var(--c-muted); max-width: 400px; margin: 0 auto 1.5rem auto;">
            <?php if ($filter !== 'all'): ?>
                There are no orders matching the "<?= e(ucfirst($filter)) ?>" filter.
            <?php else: ?>
                You haven't placed any food orders with UrbanEats yet.
            <?php endif; ?>
        </p>
        <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">
            Browse Delicious Dishes
        </a>
    </div>
<?php else: ?>

    <div class="c-card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="c-cart-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Date & Time</th>
                        <th>Fulfillment</th>
                        <th style="text-align: center;">Items</th>
                        <th style="text-align: right;">Total Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord): ?>
                        <?php
                        $statusClass = 'c-badge-' . strtolower(str_replace(' ', '_', (string)$ord['status']));
                        $payClass = 'c-badge-' . strtolower((string)($ord['payment_status'] ?? 'pending'));
                        ?>
                        <tr class="c-cart-item">
                            <td>
                                <strong style="color: var(--c-charcoal); font-family: monospace; font-size: 0.95rem;">
                                    <?= e($ord['order_number']) ?>
                                </strong>
                            </td>
                            <td style="color: var(--c-muted); font-size: 0.88rem; white-space: nowrap;">
                                <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($ord['fulfillment_method'] === 'delivery'): ?>
                                    <span style="font-size: 0.85rem; font-weight: 600; color: #1E40AF;">🛵 Delivery</span>
                                <?php else: ?>
                                    <span style="font-size: 0.85rem; font-weight: 600; color: #065F46;">🛍️ Pickup</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-weight: 600; color: var(--c-charcoal);">
                                <?= (int)$ord['item_count'] ?> dishes
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--c-charcoal); white-space: nowrap;">
                                <?= format_lkr($ord['total_amount']) ?>
                            </td>
                            <td>
                                <div style="font-size: 0.82rem; font-weight: 600; color: var(--c-charcoal);">
                                    <?= e($ord['payment_method'] ?? 'COD') ?>
                                </div>
                                <span class="c-badge <?= $payClass ?>" style="padding: 0.15rem 0.45rem; font-size: 0.72rem; margin-top: 0.2rem;">
                                    <?= e($ord['payment_status'] ?? 'Pending') ?>
                                </span>
                            </td>
                            <td>
                                <span class="c-badge <?= $statusClass ?>">
                                    ● <?= e($ord['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.4rem;">
                                    <a href="<?= base_url('orders/track.php?id=' . $ord['order_id']) ?>" class="btn btn-outline btn-sm" title="Track Live Status" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                        🚚 Track
                                    </a>
                                    <a href="<?= base_url('orders/confirmation.php?id=' . $ord['order_id']) ?>" class="btn btn-outline btn-sm" title="View Printable Invoice" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                        📄 Receipt
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--c-border); background: #FAFDFB; flex-wrap: wrap; gap: 1rem;">
                <span style="font-size: 0.85rem; color: var(--c-muted);">
                    Showing page <strong><?= $pagination['current_page'] ?></strong> of <strong><?= $pagination['total_pages'] ?></strong> (<?= $pagination['total_records'] ?> total orders)
                </span>
                <div style="display: flex; gap: 0.4rem;">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="<?= e($pagination['prev_url']) ?>" class="btn btn-outline btn-sm">
                            ← Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                        <a href="<?= e(($pagination['build_url'])($p)) ?>" class="btn btn-sm <?= $p === $pagination['current_page'] ? 'btn-primary' : 'btn-outline' ?>" style="min-width: 32px; text-align: center;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pagination['has_next']): ?>
                        <a href="<?= e($pagination['next_url']) ?>" class="btn btn-outline btn-sm">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
