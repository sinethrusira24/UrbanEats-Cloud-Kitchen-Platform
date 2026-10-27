<?php
// Checkout and order submission page.

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/component_c.php';

// 1. Mandatory Customer Authentication
require_login();

$user = current_user();
$userId = (int)current_user_id();

// 2. Cart Verification
$cartDetails = cart_get_details();
$items = $cartDetails['items'];
$subtotal = $cartDetails['subtotal'];
$totalCount = $cartDetails['count'];
$hasUnavailable = $cartDetails['has_unavailable'];

if (empty($items)) {
    flash('warning', 'Your cart is empty. Please select dishes before proceeding to checkout.');
    redirect('orders/cart.php');
}

if ($hasUnavailable) {
    flash('danger', 'Some items in your cart are currently out of stock. Please adjust quantities.');
    redirect('orders/cart.php');
}

// Variables for form state & coupon handling
$errors = [];
$fulfillmentMethod = 'delivery';
$deliveryAddress = '';
$contactPhone = '';
$orderNotes = '';
$paymentMethod = 'Cash on Delivery';
$couponCode = '';
$discountAmount = 0.00;
$finalTotal = $subtotal;
$couponInfo = null;

// Generate or retrieve idempotency token to prevent double-click submissions
if (empty($_SESSION['checkout_idempotency_token'])) {
    $_SESSION['checkout_idempotency_token'] = bin2hex(random_bytes(16));
}
$idempotencyToken = $_SESSION['checkout_idempotency_token'];

// Check for coupon applied via query parameter or POST
if (!empty($_REQUEST['coupon_code'])) {
    $couponCode = strtoupper(trim((string)$_REQUEST['coupon_code']));
    $cpnResult = validate_coupon($couponCode, $subtotal);
    if ($cpnResult['valid']) {
        $discountAmount = $cpnResult['discount_amount'];
        $finalTotal = $cpnResult['final_total'];
        $couponInfo = $cpnResult['coupon'];
    } else {
        $errors['coupon'] = $cpnResult['message'];
        $couponCode = '';
    }
}

// 3. Process Checkout Form Submission (Atomic Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    require_csrf();

    $fulfillmentMethod = strtolower(trim((string)($_POST['fulfillment_method'] ?? 'delivery')));
    $deliveryAddress = trim((string)($_POST['delivery_address'] ?? ''));
    $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
    $orderNotes = trim((string)($_POST['order_notes'] ?? ''));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'Cash on Delivery'));
    $submittedToken = trim((string)($_POST['idempotency_token'] ?? $idempotencyToken));

    // Server-side validation
    if (!in_array($fulfillmentMethod, ['delivery', 'pickup'], true)) {
        $errors['fulfillment'] = 'Please select a valid fulfillment method.';
    }

    if ($fulfillmentMethod === 'delivery' && strlen($deliveryAddress) < 6) {
        $errors['delivery_address'] = 'Please enter a complete delivery address (street, city).';
    }

    if (!preg_match('/^[0-9+ \-]{9,20}$/', $contactPhone)) {
        $errors['contact_phone'] = 'Please enter a valid phone number (e.g. 0771234567).';
    }

    $allowedPayments = ['Cash on Delivery', 'Card', 'Bank Transfer'];
    if (!in_array($paymentMethod, $allowedPayments, true)) {
        $errors['payment_method'] = 'Please select an accepted payment method.';
    }

    if (empty($errors)) {
        // Execute atomic checkout transaction
        $checkoutResult = process_checkout([
            'fulfillment_method' => $fulfillmentMethod,
            'delivery_address'   => $deliveryAddress,
            'contact_phone'      => $contactPhone,
            'order_notes'        => $orderNotes,
            'payment_method'     => $paymentMethod,
            'coupon_code'        => $couponCode !== '' ? $couponCode : null,
            'idempotency_token'  => $submittedToken,
        ]);

        if ($checkoutResult['success']) {
            // Invalidate idempotency token for future checkouts
            unset($_SESSION['checkout_idempotency_token']);

            flash('success', sprintf('🎉 Order #%s has been successfully placed!', $checkoutResult['order_number']));
            redirect('orders/confirmation.php?id=' . $checkoutResult['order_id']);
        } else {
            $errors['checkout'] = $checkoutResult['error'] ?? 'Checkout could not be completed. Please try again.';
        }
    }
}

$pageTitle = 'Checkout';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="c-page-header">
    <div>
        <h1 class="c-page-title">
            <span>🛡️</span> Secure Checkout
        </h1>
        <p class="c-page-subtitle">Confirm your fulfillment details and complete your order.</p>
    </div>
    <a href="<?= base_url('orders/cart.php') ?>" class="btn btn-outline btn-sm">
        ← Edit Shopping Cart
    </a>
</div>

<?php if (!empty($errors['checkout'])): ?>
    <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem;">
        <span>⚠️</span>
        <div><strong>Checkout Failed:</strong> <?= e($errors['checkout']) ?></div>
    </div>
<?php endif; ?>

<form action="<?= base_url('orders/checkout.php') ?>" method="POST" id="checkoutForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="place_order">
    <input type="hidden" name="idempotency_token" value="<?= e($idempotencyToken) ?>">

    <div class="c-two-column">
        <!-- Left Column: Checkout Inputs -->
        <div>
            <!-- Section 1: Customer Contact Information -->
            <div class="c-card">
                <div class="c-card-header">
                    <h3 class="c-card-title">1. Customer Information</h3>
                    <span style="font-size: 0.8rem; background: #E0E7FF; color: #3730A3; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">
                        Authenticated
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="c-form-group">
                        <label class="c-form-label">Full Name</label>
                        <input type="text" class="c-form-input" value="<?= e($user['full_name'] ?? $user['username']) ?>" readonly style="background: #F9FAF9; cursor: not-allowed;">
                    </div>
                    <div class="c-form-group">
                        <label class="c-form-label">Account Email</label>
                        <input type="text" class="c-form-input" value="<?= e($user['email'] ?? '') ?>" readonly style="background: #F9FAF9; cursor: not-allowed;">
                    </div>
                </div>

                <div class="c-form-group" style="margin-bottom: 0;">
                    <label class="c-form-label" for="contact_phone">Contact Phone Number <span style="color: var(--c-danger);">*</span></label>
                    <input type="tel" name="contact_phone" id="contact_phone" class="c-form-input" placeholder="e.g. 0771234567" required value="<?= e($contactPhone) ?>">
                    <?php if (!empty($errors['contact_phone'])): ?>
                        <div style="color: var(--c-danger); font-size: 0.82rem; margin-top: 0.35rem;"><?= e($errors['contact_phone']) ?></div>
                    <?php endif; ?>
                    <small style="color: var(--c-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                        Used for delivery updates and kitchen collection confirmation.
                    </small>
                </div>
            </div>

            <!-- Section 2: Fulfillment Method -->
            <div class="c-card">
                <div class="c-card-header">
                    <h3 class="c-card-title">2. Fulfillment Method</h3>
                </div>

                <div class="c-fulfillment-selector">
                    <label class="c-fulfillment-option <?= $fulfillmentMethod === 'delivery' ? 'active' : '' ?>" id="optDelivery">
                        <input type="radio" name="fulfillment_method" value="delivery" <?= $fulfillmentMethod === 'delivery' ? 'checked' : '' ?> onchange="updateFulfillment(this.value)">
                        <div>
                            <div style="font-weight: 700; color: var(--c-charcoal); display: flex; align-items: center; gap: 0.4rem;">
                                <span>🛵</span> Doorstep Delivery
                            </div>
                            <small style="color: var(--c-muted);">Direct to your home or office</small>
                        </div>
                    </label>

                    <label class="c-fulfillment-option <?= $fulfillmentMethod === 'pickup' ? 'active' : '' ?>" id="optPickup">
                        <input type="radio" name="fulfillment_method" value="pickup" <?= $fulfillmentMethod === 'pickup' ? 'checked' : '' ?> onchange="updateFulfillment(this.value)">
                        <div>
                            <div style="font-weight: 700; color: var(--c-charcoal); display: flex; align-items: center; gap: 0.4rem;">
                                <span>🛍️</span> Kitchen Counter Pickup
                            </div>
                            <small style="color: var(--c-muted);">Collect fresh from cloud kitchen</small>
                        </div>
                    </label>
                </div>

                <!-- Delivery Address Field (Shown for delivery) -->
                <div id="deliveryAddressGroup" style="<?= $fulfillmentMethod === 'pickup' ? 'display: none;' : '' ?>">
                    <div class="c-form-group">
                        <label class="c-form-label" for="delivery_address">Delivery Address <span style="color: var(--c-danger);">*</span></label>
                        <textarea name="delivery_address" id="delivery_address" rows="3" class="c-form-textarea" placeholder="House/Flat number, Street name, City (e.g. 45/2 Galle Road, Colombo 03)"><?= e($deliveryAddress) ?></textarea>
                        <?php if (!empty($errors['delivery_address'])): ?>
                            <div style="color: var(--c-danger); font-size: 0.82rem; margin-top: 0.35rem;"><?= e($errors['delivery_address']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pickup Notice (Shown for pickup) -->
                <div id="pickupNotice" style="<?= $fulfillmentMethod === 'delivery' ? 'display: none;' : '' ?>">
                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--c-radius-sm); padding: 1rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                        <span style="font-size: 1.5rem;">📍</span>
                        <div>
                            <strong style="color: #166534; display: block;">Kitchen Collection Hub:</strong>
                            <p style="margin: 0.25rem 0 0 0; color: #166534; font-size: 0.9rem;">
                                UrbanEats Central Kitchen, 25 Havelock Road, Colombo 05.<br>
                                <small>Your food will be packaged hot and ready for collection at the pickup counter.</small>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="c-form-group" style="margin-top: 1rem; margin-bottom: 0;">
                    <label class="c-form-label" for="order_notes">Special Kitchen Instructions (Optional)</label>
                    <input type="text" name="order_notes" id="order_notes" class="c-form-input" placeholder="e.g. Less spicy, leave at security, allergy notes" value="<?= e($orderNotes) ?>">
                </div>
            </div>

            <!-- Section 3: Simulated Payment Method -->
            <div class="c-card">
                <div class="c-card-header">
                    <h3 class="c-card-title">3. Simulated Payment Method</h3>
                    <span class="c-payment-badge">Zero Risk • Simulated Sandbox</span>
                </div>

                <p style="font-size: 0.85rem; color: var(--c-muted); margin: 0 0 1rem 0;">
                    In accordance with university assignment guidelines, payment processing is simulated. No real credit card or bank credentials are required or stored.
                </p>

                <div class="c-payment-methods">
                    <label class="c-payment-card <?= $paymentMethod === 'Cash on Delivery' ? 'active' : '' ?>">
                        <div class="c-payment-info">
                            <input type="radio" name="payment_method" value="Cash on Delivery" <?= $paymentMethod === 'Cash on Delivery' ? 'checked' : '' ?> onchange="updatePayment(this)">
                            <div>
                                <strong style="color: var(--c-charcoal); display: block;">💵 Cash on Delivery / Counter Pickup</strong>
                                <small style="color: var(--c-muted);">Pay in cash when your food is delivered or collected</small>
                            </div>
                        </div>
                        <span class="c-badge c-badge-placed">Pay on Arrival</span>
                    </label>

                    <label class="c-payment-card <?= $paymentMethod === 'Card' ? 'active' : '' ?>">
                        <div class="c-payment-info">
                            <input type="radio" name="payment_method" value="Card" <?= $paymentMethod === 'Card' ? 'checked' : '' ?> onchange="updatePayment(this)">
                            <div>
                                <strong style="color: var(--c-charcoal); display: block;">💳 Credit / Debit Card (Simulated)</strong>
                                <small style="color: var(--c-muted);">Simulated Visa / Mastercard instant authorization</small>
                            </div>
                        </div>
                        <span class="c-badge c-badge-completed">Simulated Instant</span>
                    </label>

                    <label class="c-payment-card <?= $paymentMethod === 'Bank Transfer' ? 'active' : '' ?>">
                        <div class="c-payment-info">
                            <input type="radio" name="payment_method" value="Bank Transfer" <?= $paymentMethod === 'Bank Transfer' ? 'checked' : '' ?> onchange="updatePayment(this)">
                            <div>
                                <strong style="color: var(--c-charcoal); display: block;">🏦 Direct Bank Transfer (Simulated)</strong>
                                <small style="color: var(--c-muted);">Simulated online banking transfer with generated reference</small>
                            </div>
                        </div>
                        <span class="c-badge c-badge-preparing">Pending Slip</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Right Column: Order Summary & Coupon -->
        <div>
            <!-- Promotional Coupon Widget -->
            <div class="c-card">
                <h3 class="c-card-title" style="margin-bottom: 0.75rem;">Promotional Coupon</h3>
                <p style="font-size: 0.85rem; color: var(--c-muted); margin: 0 0 0.75rem 0;">
                    Have a promo voucher? Enter code (e.g. <code>WELCOME10</code> or <code>SAVE500</code>).
                </p>

                <div class="c-coupon-box">
                    <input type="text" name="coupon_code" id="couponInput" class="c-form-input" placeholder="Coupon Code" value="<?= e($couponCode) ?>" style="text-transform: uppercase;">
                    <button type="button" class="btn btn-outline" onclick="applyCoupon()" style="white-space: nowrap;">
                        Apply
                    </button>
                </div>

                <?php if (!empty($couponInfo)): ?>
                    <div style="margin-top: 0.75rem; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.85rem; color: #166534; display: flex; justify-content: space-between; align-items: center;">
                        <span><strong><?= e($couponInfo['coupon_code']) ?></strong> (<?= e($couponInfo['discount_type'] === 'percentage' ? (int)$couponInfo['discount_value'] . '%' : format_lkr($couponInfo['discount_value'])) ?> off)</span>
                        <a href="<?= base_url('orders/checkout.php') ?>" style="color: var(--c-danger); font-size: 0.8rem; text-decoration: none;">Remove</a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['coupon'])): ?>
                    <div style="color: var(--c-danger); font-size: 0.82rem; margin-top: 0.5rem;">
                        <?= e($errors['coupon']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Review Sidebar -->
            <div class="c-card">
                <h3 class="c-card-title" style="margin-bottom: 1rem;">Order Items (<?= $totalCount ?>)</h3>

                <div style="max-height: 220px; overflow-y: auto; margin-bottom: 1rem; padding-right: 0.25rem;">
                    <?php foreach ($items as $item): ?>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; padding: 0.4rem 0; border-bottom: 1px dotted var(--c-border);">
                            <div>
                                <span style="font-weight: 600; color: var(--c-charcoal);"><?= (int)$item['quantity'] ?> ×</span>
                                <span><?= e($item['name']) ?></span>
                            </div>
                            <span style="font-weight: 600; color: var(--c-charcoal);"><?= format_lkr($item['line_total']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="c-summary-row">
                    <span>Subtotal</span>
                    <span style="font-weight: 600; color: var(--c-charcoal);"><?= format_lkr($subtotal) ?></span>
                </div>

                <?php if ($discountAmount > 0): ?>
                    <div class="c-summary-row discount">
                        <span>Coupon Discount</span>
                        <span>− <?= format_lkr($discountAmount) ?></span>
                    </div>
                <?php endif; ?>

                <div class="c-summary-row">
                    <span>Fulfillment Fee</span>
                    <span style="color: var(--c-primary); font-weight: 600;">FREE</span>
                </div>

                <div class="c-summary-row total">
                    <span>Total Amount</span>
                    <span style="color: var(--c-primary);"><?= format_lkr($finalTotal) ?></span>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="c-btn-block c-btn-primary" id="submitOrderBtn">
                        Confirm & Place Order (<?= format_lkr($finalTotal) ?>)
                    </button>
                </div>

                <div style="margin-top: 1rem; font-size: 0.78rem; color: var(--c-muted); text-align: center; line-height: 1.4;">
                    By confirming, you authorize our cloud kitchen to begin preparation. Atomic stock deduction and simulated payment records will be committed securely.
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function updateFulfillment(method) {
    const deliveryGroup = document.getElementById('deliveryAddressGroup');
    const pickupNotice = document.getElementById('pickupNotice');
    const deliveryInput = document.getElementById('delivery_address');
    const optDelivery = document.getElementById('optDelivery');
    const optPickup = document.getElementById('optPickup');

    if (method === 'pickup') {
        deliveryGroup.style.display = 'none';
        pickupNotice.style.display = 'block';
        deliveryInput.removeAttribute('required');
        optDelivery.classList.remove('active');
        optPickup.classList.add('active');
    } else {
        deliveryGroup.style.display = 'block';
        pickupNotice.style.display = 'none';
        deliveryInput.setAttribute('required', 'required');
        optPickup.classList.remove('active');
        optDelivery.classList.add('active');
    }
}

function updatePayment(radio) {
    document.querySelectorAll('.c-payment-card').forEach(card => card.classList.remove('active'));
    radio.closest('.c-payment-card').classList.add('active');
}

function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim();
    if (!code) {
        alert('Please enter a coupon code.');
        return;
    }
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('coupon_code', code);
    window.location.href = currentUrl.toString();
}

// Prevent double submission client-side as an extra UX safeguard
document.getElementById('checkoutForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitOrderBtn');
    btn.disabled = true;
    btn.innerText = 'Processing Order...';
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
