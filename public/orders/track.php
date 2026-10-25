<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Real-Time Order Tracking & Timeline
 *
 * Visualizes order lifecycle progression (Placed -> Preparing -> Ready -> Out for Delivery / Collected -> Completed),
 * completed stage timestamps, and handles customer self-cancellation for eligible placed orders.
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
    flash('danger', 'Order not found or you are not authorized to view this tracking page.');
    redirect('orders/history.php');
}

// Handle Customer Cancellation (Permitted only while still in 'Placed' state)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    require_csrf();

    if ($order['status'] !== 'Placed') {
        flash('danger', 'This order has already begun kitchen preparation and cannot be cancelled.');
        redirect('orders/track.php?id=' . $orderId);
    }

    $cancelReason = trim((string)($_POST['cancel_reason'] ?? 'Cancelled by customer'));
    $transResult = transition_order_status(
        $orderId,
        'Cancelled',
        $cancelReason !== '' ? $cancelReason : 'Customer self-cancellation',
        $currentUserId
    );

    if ($transResult['success']) {
        flash('info', 'Your order has been cancelled and any reserved stock has been restored.');
    } else {
        flash('danger', 'Cancellation failed: ' . $transResult['error']);
    }

    redirect('orders/track.php?id=' . $orderId);
}

$items = get_order_items($orderId);
$timeline = get_order_status_timeline($orderId);
$fulfillment = (string)$order['fulfillment_method'];
$currentStatus = (string)$order['status'];

// Define standard chronological stages
$stages = ($fulfillment === 'pickup')
    ? ['Placed', 'Preparing', 'Ready', 'Collected', 'Completed']
    : ['Placed', 'Preparing', 'Ready', 'Out for Delivery', 'Completed'];

// Map stage timestamps from history
$stageTimestamps = [];
foreach ($timeline as $th) {
    $stageTimestamps[$th['new_status']] = $th['changed_at'];
}

// Determine stage indexes for UI stepper
$currentIndex = array_search($currentStatus, $stages, true);
$isCancelled = ($currentStatus === 'Cancelled');

$pageTitle = 'Tracking Order #' . e($order['order_number']);
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="c-page-header">
    <div>
        <h1 class="c-page-title">
            <span>🚚</span> Live Order Tracking
        </h1>
        <p class="c-page-subtitle">
            Order Reference: <strong><?= e($order['order_number']) ?></strong> • Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-outline btn-sm" onclick="window.location.reload();">
            🔄 Refresh Status
        </button>
        <a href="<?= base_url('orders/confirmation.php?id=' . $orderId) ?>" class="btn btn-outline btn-sm">
            📄 View Receipt
        </a>
    </div>
</div>

<?php if ($isCancelled): ?>
    <!-- Cancelled Order Banner -->
    <div class="c-card" style="border-left: 5px solid var(--c-danger); background: #FFFBFB;">
        <div style="display: flex; gap: 1rem; align-items: flex-start;">
            <span style="font-size: 2.2rem;">❌</span>
            <div>
                <h2 style="font-size: 1.35rem; color: var(--c-danger); margin: 0 0 0.35rem 0;">Order Cancelled</h2>
                <p style="color: var(--c-text); margin: 0; font-size: 0.95rem;">
                    This order was cancelled on <strong><?= date('d M Y, h:i A', strtotime($stageTimestamps['Cancelled'] ?? $order['updated_at'])) ?></strong>.
                    Any portion reservations have been returned to kitchen stock.
                </p>
                <?php
                // Find cancellation note if exists
                foreach ($timeline as $tItem) {
                    if ($tItem['new_status'] === 'Cancelled' && !empty($tItem['notes'])) {
                        echo '<p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--c-muted);"><strong>Reason:</strong> ' . e($tItem['notes']) . '</p>';
                        break;
                    }
                }
                ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Active / Completed Visual Stepper -->
    <div class="c-card" style="padding: 2rem 1.5rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <span class="c-badge c-badge-<?= strtolower(str_replace(' ', '_', $currentStatus)) ?>" style="font-size: 1rem; padding: 0.4rem 1.25rem;">
                Current Status: <?= e($currentStatus) ?>
            </span>
            <?php if ($currentStatus === 'Placed'): ?>
                <p style="margin: 0.5rem 0 0 0; color: var(--c-muted); font-size: 0.9rem;">
                    Your order has been received! Cloud kitchen staff will start preparation shortly.
                </p>
            <?php elseif ($currentStatus === 'Preparing'): ?>
                <p style="margin: 0.5rem 0 0 0; color: var(--c-muted); font-size: 0.9rem;">
                    Our head cloud chef is currently preparing and wok-tossing your dishes.
                </p>
            <?php elseif ($currentStatus === 'Ready'): ?>
                <p style="margin: 0.5rem 0 0 0; color: #155E75; font-size: 0.9rem; font-weight: 600;">
                    Dishes are cooked, sealed, and waiting for <?= $fulfillment === 'delivery' ? 'driver dispatch' : 'counter collection' ?>.
                </p>
            <?php elseif ($currentStatus === 'Out for Delivery'): ?>
                <p style="margin: 0.5rem 0 0 0; color: #5B21B6; font-size: 0.9rem; font-weight: 600;">
                    🛵 Your delivery driver is on the road heading to your delivery address!
                </p>
            <?php elseif ($currentStatus === 'Collected'): ?>
                <p style="margin: 0.5rem 0 0 0; color: #065F46; font-size: 0.9rem;">
                    Food collected at our kitchen counter. Enjoy your meal!
                </p>
            <?php elseif ($currentStatus === 'Completed'): ?>
                <p style="margin: 0.5rem 0 0 0; color: #166534; font-size: 0.9rem; font-weight: 600;">
                    ✅ Delivered and completed! Thank you for ordering with UrbanEats.
                </p>
            <?php endif; ?>
        </div>

        <div class="c-stepper">
            <?php foreach ($stages as $idx => $stg): ?>
                <?php
                $isCompleted = ($currentIndex !== false && $idx < $currentIndex) || ($currentStatus === 'Completed');
                $isCurrent = ($currentIndex !== false && $idx === $currentIndex) && ($currentStatus !== 'Completed');
                $stepClass = $isCompleted ? 'completed' : ($isCurrent ? 'current' : 'pending');
                $timeText = isset($stageTimestamps[$stg]) ? date('h:i A', strtotime($stageTimestamps[$stg])) : null;
                ?>
                <div class="c-step-item <?= $stepClass ?>">
                    <div class="c-step-circle">
                        <?php if ($isCompleted): ?>
                            ✓
                        <?php else: ?>
                            <?= $idx + 1 ?>
                        <?php endif; ?>
                    </div>
                    <div class="c-step-title"><?= e($stg) ?></div>
                    <?php if ($timeText): ?>
                        <div class="c-step-time"><?= $timeText ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Customer Self-Cancel Button (Only while Placed) -->
        <?php if ($currentStatus === 'Placed'): ?>
            <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--c-border);">
                <form action="<?= base_url('orders/track.php?id=' . $orderId) ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? Stock will be immediately restored.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_order">
                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--c-danger); border-color: #FECACA;">
                        ✕ Cancel Order
                    </button>
                    <span style="font-size: 0.8rem; color: var(--c-muted); margin-left: 0.5rem;">(Available before kitchen preparation starts)</span>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="c-two-column">
    <!-- Left: Order Details & Address -->
    <div>
        <div class="c-card">
            <h3 class="c-card-title">Fulfillment Details</h3>

            <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.95rem;">
                <div>
                    <span style="color: var(--c-muted);">Fulfillment Mode:</span>
                    <strong style="color: var(--c-charcoal); text-transform: capitalize; margin-left: 0.35rem;">
                        <?= $order['fulfillment_method'] === 'delivery' ? '🛵 Doorstep Delivery' : '🛍️ Counter Pickup' ?>
                    </strong>
                </div>

                <div>
                    <span style="color: var(--c-muted);">Customer:</span>
                    <strong style="color: var(--c-charcoal); margin-left: 0.35rem;"><?= e($order['customer_name']) ?></strong>
                </div>

                <div>
                    <span style="color: var(--c-muted);">Contact Telephone:</span>
                    <strong style="color: var(--c-charcoal); margin-left: 0.35rem;"><?= e($order['contact_phone']) ?></strong>
                </div>

                <?php if ($order['fulfillment_method'] === 'delivery'): ?>
                    <div>
                        <span style="color: var(--c-muted); display: block; margin-bottom: 0.2rem;">Delivery Destination:</span>
                        <div style="background: #F8FAF8; padding: 0.75rem; border-radius: var(--c-radius-sm); border: 1px solid var(--c-border); color: var(--c-text);">
                            <?= nl2br(e($order['delivery_address'])) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div>
                        <span style="color: var(--c-muted); display: block; margin-bottom: 0.2rem;">Pickup Location:</span>
                        <div style="background: #F8FAF8; padding: 0.75rem; border-radius: var(--c-radius-sm); border: 1px solid var(--c-border); color: var(--c-text);">
                            UrbanEats Central Kitchen, 25 Havelock Road, Colombo 05.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($order['order_notes'])): ?>
                    <div>
                        <span style="color: var(--c-muted);">Instructions:</span>
                        <em style="color: var(--c-charcoal);"><?= e($order['order_notes']) ?></em>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ordered Dishes -->
        <div class="c-card">
            <h3 class="c-card-title">Dishes in this Order</h3>
            <div style="margin-top: 1rem;">
                <?php foreach ($items as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px dotted var(--c-border);">
                        <div>
                            <strong style="color: var(--c-charcoal);"><?= (int)$item['quantity'] ?> × <?= e($item['dish_name']) ?></strong>
                            <?php if (!empty($item['category_name'])): ?>
                                <small style="display: block; color: var(--c-muted);"><?= e($item['category_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <span style="font-weight: 600; color: var(--c-charcoal);"><?= format_lkr($item['line_total']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="c-summary-row total" style="margin-top: 0.75rem;">
                <span>Total Amount</span>
                <span style="color: var(--c-primary);"><?= format_lkr($order['total_amount']) ?></span>
            </div>
        </div>
    </div>

    <!-- Right: Detailed Status Audit Log Timeline -->
    <div>
        <div class="c-card">
            <h3 class="c-card-title">Lifecycle Activity Log</h3>
            <p style="font-size: 0.85rem; color: var(--c-muted); margin: 0 0 1rem 0;">
                Recorded status changes and staff operational updates.
            </p>

            <div style="position: relative; padding-left: 1.5rem; border-left: 2px solid var(--c-border); margin-left: 0.5rem;">
                <?php foreach ($timeline as $entry): ?>
                    <div style="position: relative; margin-bottom: 1.5rem;">
                        <!-- Dot -->
                        <div style="position: absolute; left: -1.95rem; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: var(--c-primary); border: 2px solid #FFF;"></div>

                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                            <strong style="color: var(--c-charcoal); font-size: 0.95rem;">
                                <?= e($entry['new_status']) ?>
                            </strong>
                            <small style="color: #9CA3AF; font-size: 0.78rem;">
                                <?= date('h:i A', strtotime($entry['changed_at'])) ?>
                            </small>
                        </div>

                        <?php if (!empty($entry['notes'])): ?>
                            <div style="font-size: 0.85rem; color: var(--c-text); margin-top: 0.2rem;">
                                <?= e($entry['notes']) ?>
                            </div>
                        <?php endif; ?>

                        <div style="font-size: 0.75rem; color: var(--c-muted); margin-top: 0.25rem;">
                            <?= date('d M Y', strtotime($entry['changed_at'])) ?>
                            <?php if (!empty($entry['changed_by_name'])): ?>
                                • By <?= e($entry['changed_by_name']) ?> (<?= e($entry['changed_by_role'] ?? 'Staff') ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$isCancelled && $currentStatus !== 'Completed'): ?>
<script>
    // Automatically refresh tracking status every 15 seconds while order is in kitchen progress
    setTimeout(function () {
        window.location.reload();
    }, 15000);
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
