<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Order Confirmation & Printable Invoice
 *
 * Displays post-checkout confirmation, order receipt, and serves as an official
 * printable tax invoice formatted with print-specific CSS.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/component_c.php';

require_login();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    flash('danger', 'Invalid order identifier.');
    redirect('orders/history.php');
}

$currentUserId = (int)current_user_id();
$order = get_order_by_id($orderId, $currentUserId);

if (!$order) {
    http_response_code(403);
    flash('danger', 'Order not found or you are not authorized to view this receipt.');
    redirect('orders/history.php');
}

$items = get_order_items($orderId);
$payment = get_order_payment($orderId);

$pageTitle = 'Order Confirmation #' . e($order['order_number']);
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Print-Only Official Invoice Header -->
<div class="c-invoice-print-header" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h1 style="margin: 0; font-size: 24pt; color: #167A35;">UrbanEats</h1>
            <p style="margin: 4px 0 0 0; font-size: 10pt; color: #555;">
                Cloud Kitchen Platform • Central Kitchen Hub<br>
                25 Havelock Road, Colombo 05, Sri Lanka<br>
                Tel: +94 11 234 5678 • Email: orders@urbaneats.lk
            </p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 18pt;">TAX INVOICE</h2>
            <p style="margin: 4px 0 0 0; font-size: 10pt;">
                <strong>Invoice #:</strong> <?= e($order['order_number']) ?><br>
                <strong>Date:</strong> <?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?>
            </p>
        </div>
    </div>
</div>

<!-- Screen Confirmation Header -->
<div class="c-page-header c-no-print">
    <div>
        <h1 class="c-page-title">
            <span style="color: var(--c-primary);">🎉</span> Order Confirmed!
        </h1>
        <p class="c-page-subtitle">
            Thank you, <?= e($order['customer_name']) ?>. Your order has been placed and received by our kitchen.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
            🖨️ Print Invoice
        </button>
        <a href="<?= base_url('orders/track.php?id=' . $order['order_id']) ?>" class="btn btn-primary btn-sm">
            🚚 Track Live Status
        </a>
    </div>
</div>

<div class="c-card" style="margin-bottom: 2rem;">
    <!-- Order Meta Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--c-border);">
        <div>
            <small style="color: var(--c-muted); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; font-weight: 600;">Order Reference</small>
            <div style="font-size: 1.15rem; font-weight: 700; color: var(--c-charcoal); margin-top: 0.2rem;">
                <?= e($order['order_number']) ?>
            </div>
            <div style="margin-top: 0.35rem;">
                <?php
                $statusClass = 'c-badge-' . strtolower(str_replace(' ', '_', (string)$order['status']));
                ?>
                <span class="c-badge <?= $statusClass ?>">
                    ● <?= e($order['status']) ?>
                </span>
            </div>
        </div>

        <div>
            <small style="color: var(--c-muted); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; font-weight: 600;">Order Placed At</small>
            <div style="font-weight: 600; color: var(--c-charcoal); margin-top: 0.2rem;">
                <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
            </div>
            <small style="color: var(--c-muted);">Timezone: Asia/Colombo</small>
        </div>

        <div>
            <small style="color: var(--c-muted); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; font-weight: 600;">Fulfillment Mode</small>
            <div style="font-weight: 700; color: var(--c-charcoal); margin-top: 0.2rem; text-transform: capitalize;">
                <?= $order['fulfillment_method'] === 'delivery' ? '🛵 Doorstep Delivery' : '🛍️ Kitchen Pickup' ?>
            </div>
            <small style="color: var(--c-muted);">Contact: <?= e($order['contact_phone']) ?></small>
        </div>

        <div>
            <small style="color: var(--c-muted); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; font-weight: 600;">Payment State</small>
            <div style="font-weight: 600; color: var(--c-charcoal); margin-top: 0.2rem;">
                <?= e($payment['payment_method'] ?? 'Simulated') ?>
            </div>
            <div style="margin-top: 0.25rem;">
                <span class="c-badge c-badge-<?= strtolower((string)($payment['payment_status'] ?? 'pending')) ?>">
                    <?= e($payment['payment_status'] ?? 'Pending') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Fulfillment Destination -->
    <div style="margin: 1.5rem 0; background: #F8FAF8; border-radius: var(--c-radius-sm); padding: 1rem 1.25rem;">
        <?php if ($order['fulfillment_method'] === 'delivery'): ?>
            <strong style="color: var(--c-charcoal); font-size: 0.95rem;">Delivery Destination:</strong>
            <p style="margin: 0.35rem 0 0 0; color: var(--c-text); font-size: 0.95rem;">
                <?= nl2br(e($order['delivery_address'])) ?>
            </p>
        <?php else: ?>
            <strong style="color: var(--c-charcoal); font-size: 0.95rem;">Kitchen Collection Hub:</strong>
            <p style="margin: 0.35rem 0 0 0; color: var(--c-text); font-size: 0.95rem;">
                UrbanEats Central Kitchen, 25 Havelock Road, Colombo 05.<br>
                <small style="color: var(--c-muted);">Please present order reference <strong><?= e($order['order_number']) ?></strong> at the collection counter.</small>
            </p>
        <?php endif; ?>

        <?php if (!empty($order['order_notes'])): ?>
            <div style="margin-top: 0.6rem; font-size: 0.88rem; color: var(--c-muted);">
                <strong>Special Notes:</strong> <?= e($order['order_notes']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Itemized Purchases Table -->
    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--c-charcoal); margin: 1.5rem 0 0.85rem 0;">
        Itemized Purchase Summary
    </h3>

    <div style="overflow-x: auto;">
        <table class="c-cart-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Dish Description</th>
                    <th style="text-align: right; width: 140px;">Unit Price (Snapshot)</th>
                    <th style="text-align: center; width: 100px;">Qty</th>
                    <th style="text-align: right; width: 140px;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                    <tr>
                        <td style="color: var(--c-muted);"><?= $i++ ?></td>
                        <td>
                            <strong style="color: var(--c-charcoal);"><?= e($item['dish_name']) ?></strong>
                            <?php if (!empty($item['category_name'])): ?>
                                <div style="font-size: 0.8rem; color: var(--c-muted);"><?= e($item['category_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; color: var(--c-charcoal); font-weight: 600;">
                            <?= format_lkr($item['unit_price']) ?>
                        </td>
                        <td style="text-align: center; font-weight: 700; color: var(--c-charcoal);">
                            <?= (int)$item['quantity'] ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: var(--c-charcoal);">
                            <?= format_lkr($item['line_total']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Financial Breakdown Table -->
    <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
        <div style="width: 340px;">
            <div class="c-summary-row">
                <span>Subtotal</span>
                <span style="font-weight: 600; color: var(--c-charcoal);"><?= format_lkr($order['subtotal']) ?></span>
            </div>

            <?php if ((float)$order['discount_amount'] > 0): ?>
                <div class="c-summary-row discount">
                    <span>Coupon Discount (<?= e($order['coupon_code'] ?? 'Promo') ?>)</span>
                    <span>− <?= format_lkr($order['discount_amount']) ?></span>
                </div>
            <?php endif; ?>

            <div class="c-summary-row">
                <span>Delivery / Service Fee</span>
                <span style="color: var(--c-primary); font-weight: 600;">FREE</span>
            </div>

            <div class="c-summary-row total">
                <span>Grand Total</span>
                <span style="color: var(--c-primary);"><?= format_lkr($order['total_amount']) ?></span>
            </div>
        </div>
    </div>

    <!-- Simulated Payment Detail Footnote -->
    <div style="margin-top: 2rem; padding: 1rem; background: #FAFDFB; border: 1px dashed var(--c-border); border-radius: var(--c-radius-sm); font-size: 0.85rem; color: var(--c-muted);">
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <strong>Simulated Payment Reference:</strong> <code><?= e($payment['transaction_reference'] ?? 'SIM-N/A') ?></code>
            </div>
            <div>
                <strong>Customer Account:</strong> <?= e($order['customer_email']) ?>
            </div>
        </div>
        <div style="margin-top: 0.35rem; font-size: 0.8rem; color: #6B7280;">
            This order and invoice were generated for university course demonstration purposes. All financial calculations and stock adjustments have been atomically committed to the platform database.
        </div>
    </div>
</div>

<!-- Bottom Navigation Actions -->
<div class="c-no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <a href="<?= base_url('orders/history.php') ?>" class="btn btn-outline">
        ← View My Order History
    </a>
    <div style="display: flex; gap: 0.75rem;">
        <a href="<?= base_url('menu/index.php') ?>" class="btn btn-outline">
            Order Again
        </a>
        <a href="<?= base_url('orders/track.php?id=' . $order['order_id']) ?>" class="btn btn-primary">
            Track Live Preparation →
        </a>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
