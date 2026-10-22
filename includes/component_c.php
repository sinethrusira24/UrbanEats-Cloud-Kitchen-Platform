<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Ordering, Payment and Tracking
 *
 * Core Business Logic, Shopping Cart Engine, Checkout Transaction Manager,
 * Concurrency-Safe Order State Machine, and Reporting Analytics.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/component_b.php';

// =============================================================================
// 1. SESSION-BASED SHOPPING CART MANAGEMENT
// =============================================================================

/**
 * Retrieve the raw cart from the session.
 * Stores only [dish_id => quantity] mappings (never trusted prices or totals).
 *
 * @return array<int, int>
 */
function cart_get(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

/**
 * Get total quantity count of all items currently in cart.
 *
 * @return int
 */
function cart_count(): int
{
    $cart = cart_get();
    return array_sum($cart);
}

/**
 * Add a dish to the shopping cart.
 *
 * @param int      $dishId   Dish identifier
 * @param int      $quantity Portions to add (must be >= 1)
 * @param PDO|null $pdo
 * @return array{success: bool, message: string}
 */
function cart_add(int $dishId, int $quantity = 1, ?PDO $pdo = null): array
{
    if ($dishId <= 0) {
        return ['success' => false, 'message' => 'Invalid dish identifier.'];
    }

    if ($quantity <= 0 || $quantity > 50) {
        return ['success' => false, 'message' => 'Quantity must be between 1 and 50 portions.'];
    }

    $pdo = $pdo ?? get_db_connection();
    $dish = get_dish_details($dishId, $pdo);

    if (!$dish || (int)$dish['is_active'] !== 1) {
        return ['success' => false, 'message' => 'This dish is currently unavailable on the menu.'];
    }

    $currentStock = (int)$dish['qty_available'];
    if ($currentStock <= 0) {
        return ['success' => false, 'message' => sprintf('Sorry, "%s" is currently out of stock.', $dish['name'])];
    }

    $cart = cart_get();
    $existingQty = $cart[$dishId] ?? 0;
    $newQty = $existingQty + $quantity;

    if ($newQty > $currentStock) {
        return [
            'success' => false,
            'message' => sprintf(
                'Cannot add %d portions of "%s". Only %d portions available in total (%d already in your cart).',
                $quantity,
                $dish['name'],
                $currentStock,
                $existingQty
            ),
        ];
    }

    if ($newQty > 50) {
        return ['success' => false, 'message' => 'Maximum limit of 50 portions per dish reached.'];
    }

    $cart[$dishId] = $newQty;
    $_SESSION['cart'] = $cart;

    return [
        'success' => true,
        'message' => sprintf('Added %d × "%s" to your cart.', $quantity, $dish['name']),
    ];
}

/**
 * Update the quantity of an item in the cart.
 * If quantity is 0, the item is removed.
 *
 * @param int      $dishId
 * @param int      $quantity
 * @param PDO|null $pdo
 * @return array{success: bool, message: string}
 */
function cart_update(int $dishId, int $quantity, ?PDO $pdo = null): array
{
    if ($dishId <= 0) {
        return ['success' => false, 'message' => 'Invalid dish identifier.'];
    }

    $cart = cart_get();

    if ($quantity <= 0) {
        unset($cart[$dishId]);
        $_SESSION['cart'] = $cart;
        return ['success' => true, 'message' => 'Item removed from your cart.'];
    }

    if ($quantity > 50) {
        return ['success' => false, 'message' => 'Maximum allowed quantity is 50 portions.'];
    }

    $pdo = $pdo ?? get_db_connection();
    $dish = get_dish_details($dishId, $pdo);

    if (!$dish || (int)$dish['is_active'] !== 1) {
        unset($cart[$dishId]);
        $_SESSION['cart'] = $cart;
        return ['success' => false, 'message' => 'This dish is no longer available and was removed from your cart.'];
    }

    $currentStock = (int)$dish['qty_available'];
    if ($quantity > $currentStock) {
        return [
            'success' => false,
            'message' => sprintf('Only %d portions of "%s" are available.', $currentStock, $dish['name']),
        ];
    }

    $cart[$dishId] = $quantity;
    $_SESSION['cart'] = $cart;

    return ['success' => true, 'message' => 'Cart quantity updated successfully.'];
}

/**
 * Remove an item completely from the cart.
 *
 * @param int $dishId
 */
function cart_remove(int $dishId): void
{
    $cart = cart_get();
    if (isset($cart[$dishId])) {
        unset($cart[$dishId]);
        $_SESSION['cart'] = $cart;
    }
}

/**
 * Clear all items from the shopping cart.
 */
function cart_clear(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $_SESSION['cart'] = [];
}

/**
 * Fetch authoritative dish and stock data for all items currently in the cart.
 * Performs fresh database lookup to ensure up-to-date pricing and availability.
 *
 * @param PDO|null $pdo
 * @return array{
 *   items: array<int, array{
 *     dish_id: int,
 *     name: string,
 *     category_name: string,
 *     image_path: ?string,
 *     unit_price: float,
 *     quantity: int,
 *     line_total: float,
 *     qty_available: int,
 *     is_active: bool,
 *     is_available: bool,
 *     status_warning: ?string
 *   }>,
 *   subtotal: float,
 *   count: int,
 *   has_unavailable: bool
 * }
 */
function cart_get_details(?PDO $pdo = null): array
{
    $cart = cart_get();
    if (empty($cart)) {
        return [
            'items'           => [],
            'subtotal'        => 0.00,
            'count'           => 0,
            'has_unavailable' => false,
        ];
    }

    $pdo = $pdo ?? get_db_connection();
    $dishIds = array_keys($cart);

    $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
    $sql = "SELECT d.dish_id, d.category_id, d.name, d.price, d.image_path, d.is_active,
                   c.name AS category_name,
                   COALESCE(i.qty_available, 0) AS qty_available,
                   COALESCE(i.reorder_level, 5) AS reorder_level
            FROM dishes d
            JOIN categories c ON d.category_id = c.category_id
            LEFT JOIN inventory i ON d.dish_id = i.dish_id
            WHERE d.dish_id IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($dishIds);
    $rows = $stmt->fetchAll();

    $dishMap = [];
    foreach ($rows as $row) {
        $dishMap[(int)$row['dish_id']] = $row;
    }

    $items = [];
    $subtotalCents = 0;
    $totalCount = 0;
    $hasUnavailable = false;

    foreach ($cart as $dishId => $qty) {
        $dishId = (int)$dishId;
        $qty = (int)$qty;

        if (!isset($dishMap[$dishId])) {
            $hasUnavailable = true;
            $items[] = [
                'dish_id'        => $dishId,
                'name'           => 'Unavailable Dish',
                'category_name'  => 'Unknown',
                'image_path'     => null,
                'unit_price'     => 0.00,
                'quantity'       => $qty,
                'line_total'     => 0.00,
                'qty_available'  => 0,
                'is_active'      => false,
                'is_available'   => false,
                'status_warning' => 'This dish has been removed from the menu.',
            ];
            continue;
        }

        $row = $dishMap[$dishId];
        $isActive = (int)$row['is_active'] === 1;
        $qtyAvailable = (int)$row['qty_available'];
        $unitPrice = (float)$row['price'];
        $unitPriceCents = (int)round($unitPrice * 100);

        $lineTotalCents = $unitPriceCents * $qty;
        $lineTotal = round($lineTotalCents / 100, 2);

        $isAvailable = $isActive && ($qtyAvailable >= $qty);
        $warning = null;

        if (!$isActive) {
            $warning = 'This dish is currently disabled.';
            $hasUnavailable = true;
        } elseif ($qtyAvailable <= 0) {
            $warning = 'This dish is currently out of stock.';
            $hasUnavailable = true;
        } elseif ($qtyAvailable < $qty) {
            $warning = sprintf('Only %d portions available (you requested %d).', $qtyAvailable, $qty);
            $hasUnavailable = true;
        }

        if ($isAvailable) {
            $subtotalCents += $lineTotalCents;
            $totalCount += $qty;
        }

        $items[] = [
            'dish_id'        => $dishId,
            'name'           => $row['name'],
            'category_name'  => $row['category_name'],
            'image_path'     => $row['image_path'],
            'unit_price'     => $unitPrice,
            'quantity'       => $qty,
            'line_total'     => $lineTotal,
            'qty_available'  => $qtyAvailable,
            'is_active'      => $isActive,
            'is_available'   => $isAvailable,
            'status_warning' => $warning,
        ];
    }

    return [
        'items'           => $items,
        'subtotal'        => round($subtotalCents / 100, 2),
        'count'           => $totalCount,
        'has_unavailable' => $hasUnavailable,
    ];
}

// =============================================================================
// 2. COUPON VALIDATION & DISCOUNT ENGINE
// =============================================================================

/**
 * Validate a promotional coupon code and calculate the exact discount.
 *
 * @param string   $couponCode Raw entered code
 * @param float    $subtotal   Current eligible order subtotal
 * @param PDO|null $pdo
 * @return array{
 *   valid: bool,
 *   message: string,
 *   coupon: ?array,
 *   discount_amount: float,
 *   final_total: float
 * }
 */
function validate_coupon(string $couponCode, float $subtotal, ?PDO $pdo = null): array
{
    $code = strtoupper(trim($couponCode));
    if ($code === '') {
        return [
            'valid'           => false,
            'message'         => 'Please enter a coupon code.',
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT coupon_id, coupon_code, discount_type, discount_value, minimum_order_value,
                valid_from, expiry_date, usage_limit, usage_count, is_active
         FROM coupons
         WHERE coupon_code = :code
         LIMIT 1'
    );
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return [
            'valid'           => false,
            'message'         => sprintf('Coupon code "%s" does not exist.', $code),
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    if ((int)$coupon['is_active'] !== 1) {
        return [
            'valid'           => false,
            'message'         => 'This coupon is currently inactive.',
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    $now = date('Y-m-d H:i:s');
    if ($coupon['valid_from'] > $now) {
        return [
            'valid'           => false,
            'message'         => 'This coupon promotion has not started yet.',
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    if ($coupon['expiry_date'] < $now) {
        return [
            'valid'           => false,
            'message'         => 'This coupon has expired.',
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    if ((int)$coupon['usage_count'] >= (int)$coupon['usage_limit']) {
        return [
            'valid'           => false,
            'message'         => 'This coupon has reached its maximum redemption limit.',
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    $minOrder = (float)$coupon['minimum_order_value'];
    if ($subtotal < $minOrder) {
        return [
            'valid'           => false,
            'message'         => sprintf(
                'Minimum order value of %s required to apply this coupon (current: %s).',
                format_lkr($minOrder),
                format_lkr($subtotal)
            ),
            'coupon'          => null,
            'discount_amount' => 0.00,
            'final_total'     => $subtotal,
        ];
    }

    // Calculate discount using integer cents
    $subtotalCents = (int)round($subtotal * 100);
    $discountVal = (float)$coupon['discount_value'];
    $discountCents = 0;

    if ($coupon['discount_type'] === 'percentage') {
        $discountCents = (int)round(($subtotalCents * $discountVal) / 100);
    } else { // fixed
        $discountCents = (int)round($discountVal * 100);
    }

    // Capping: discount cannot exceed eligible subtotal
    if ($discountCents > $subtotalCents) {
        $discountCents = $subtotalCents;
    }

    $finalTotalCents = max(0, $subtotalCents - $discountCents);
    $discountAmount = round($discountCents / 100, 2);
    $finalTotal = round($finalTotalCents / 100, 2);

    return [
        'valid'           => true,
        'message'         => sprintf('Coupon applied! You saved %s.', format_lkr($discountAmount)),
        'coupon'          => $coupon,
        'discount_amount' => $discountAmount,
        'final_total'     => $finalTotal,
    ];
}

// =============================================================================
// 3. ATOMIC CHECKOUT TRANSACTION ENGINE
// =============================================================================

/**
 * Execute the complete checkout operation as a single, atomic database transaction.
 *
 * Operations executed within the transaction:
 * 1. Concurrency-safe row locking on dishes, inventory, and coupon.
 * 2. Verification of active status, prices, and portion availability.
 * 3. Generation of unique order number and idempotency constraint validation.
 * 4. Insertion of the primary `orders` row.
 * 5. Insertion of itemized `order_items` snapshots.
 * 6. Concurrency-safe stock deduction via Component B's `reduce_stock()`.
 * 7. Insertion of simulated payment record into `payments`.
 * 8. Insertion of initial order stage progression into `order_status_history`.
 * 9. Atomically incrementing coupon redemption count (if used).
 * 10. Automatic rollback on any constraint violation, insufficient stock, or exception.
 *
 * @param array{
 *   fulfillment_method: string,
 *   delivery_address: ?string,
 *   contact_phone: string,
 *   order_notes: ?string,
 *   payment_method: string,
 *   coupon_code: ?string,
 *   idempotency_token: string
 * } $data
 * @param PDO|null $pdo
 * @return array{
 *   success: bool,
 *   order_id: ?int,
 *   order_number: ?string,
 *   error: ?string
 * }
 */
function process_checkout(array $data, ?PDO $pdo = null): array
{
    if (!is_logged_in()) {
        return ['success' => false, 'order_id' => null, 'order_number' => null, 'error' => 'You must be logged in to place an order.'];
    }

    $userId = (int)current_user_id();
    $cart = cart_get();

    if (empty($cart)) {
        return ['success' => false, 'order_id' => null, 'order_number' => null, 'error' => 'Your shopping cart is empty.'];
    }

    // Input sanitization & validation
    $fulfillmentMethod = strtolower(trim((string)($data['fulfillment_method'] ?? 'delivery')));
    if (!in_array($fulfillmentMethod, ['delivery', 'pickup'], true)) {
        return ['success' => false, 'order_id' => null, 'order_number' => null, 'error' => 'Please select a valid fulfillment method (Delivery or Pickup).'];
    }

    $contactPhone = trim((string)($data['contact_phone'] ?? ''));
    if (!preg_match('/^[0-9+ \-]{9,20}$/', $contactPhone)) {
        return ['success' => false, 'order_id' => null, 'order_number' => null, 'error' => 'Please provide a valid contact phone number (e.g. 0771234567).'];
    }

    $deliveryAddress = null;
    if ($fulfillmentMethod === 'delivery') {
        $deliveryAddress = trim((string)($data['delivery_address'] ?? ''));
        if (strlen($deliveryAddress) < 6) {
            return ['success' => false, 'order_id' => null, 'order_number' => null, 'error' => 'Please provide a complete delivery street address.'];
        }
    }

    $paymentMethod = trim((string)($data['payment_method'] ?? ''));
    $allowedPaymentMethods = ['Cash on Delivery', 'Card', 'Bank Transfer'];
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        return ['success' => false, 'order_id' => null, 'order_number' => null, 'error' => 'Please select an accepted payment method.'];
    }

    $orderNotes = trim((string)($data['order_notes'] ?? ''));
    $couponCode = trim((string)($data['coupon_code'] ?? ''));
    $idempotencyToken = trim((string)($data['idempotency_token'] ?? ''));

    if ($idempotencyToken === '') {
        $idempotencyToken = bin2hex(random_bytes(16));
    }

    $pdo = $pdo ?? get_db_connection();

    // Check if this checkout submission was already processed (Idempotency defense)
    $stmtCheckIdemp = $pdo->prepare('SELECT order_id, order_number FROM orders WHERE idempotency_token = :token LIMIT 1');
    $stmtCheckIdemp->execute([':token' => $idempotencyToken]);
    $existingOrder = $stmtCheckIdemp->fetch();

    if ($existingOrder) {
        // Return existing order successfully without re-charging or re-deducting stock
        cart_clear();
        return [
            'success'      => true,
            'order_id'     => (int)$existingOrder['order_id'],
            'order_number' => (string)$existingOrder['order_number'],
            'error'        => null,
        ];
    }

    // BEGIN ATOMIC DATABASE TRANSACTION
    $pdo->beginTransaction();

    try {
        // 1. Fetch & lock dishes and inventory to prevent concurrent stock race conditions
        $dishIds = array_keys($cart);
        $placeholders = implode(',', array_fill(0, count($dishIds), '?'));

        $sqlLock = "SELECT d.dish_id, d.name, d.price, d.is_active,
                           COALESCE(i.qty_available, 0) AS qty_available
                    FROM dishes d
                    LEFT JOIN inventory i ON d.dish_id = i.dish_id
                    WHERE d.dish_id IN ($placeholders)
                    FOR UPDATE";

        $stmtLock = $pdo->prepare($sqlLock);
        $stmtLock->execute($dishIds);
        $lockedDishes = $stmtLock->fetchAll();

        $dishLookup = [];
        foreach ($lockedDishes as $d) {
            $dishLookup[(int)$d['dish_id']] = $d;
        }

        $subtotalCents = 0;
        $orderItemsPayload = [];

        foreach ($cart as $dId => $qty) {
            $dId = (int)$dId;
            $qty = (int)$qty;

            if (!isset($dishLookup[$dId])) {
                throw new RuntimeException("A dish in your cart is no longer available.");
            }

            $dishInfo = $dishLookup[$dId];
            if ((int)$dishInfo['is_active'] !== 1) {
                throw new RuntimeException(sprintf('Dish "%s" is inactive and cannot be ordered.', $dishInfo['name']));
            }

            $availableStock = (int)$dishInfo['qty_available'];
            if ($availableStock < $qty) {
                throw new RuntimeException(sprintf(
                    'Insufficient stock for "%s". Requested: %d, Available: %d.',
                    $dishInfo['name'],
                    $qty,
                    $availableStock
                ));
            }

            $unitPrice = (float)$dishInfo['price'];
            $unitPriceCents = (int)round($unitPrice * 100);
            $lineTotalCents = $unitPriceCents * $qty;

            $subtotalCents += $lineTotalCents;

            $orderItemsPayload[] = [
                'dish_id'    => $dId,
                'dish_name'  => $dishInfo['name'],
                'quantity'   => $qty,
                'unit_price' => $unitPrice,
                'line_total' => round($lineTotalCents / 100, 2),
            ];
        }

        $subtotal = round($subtotalCents / 100, 2);
        $discountAmount = 0.00;
        $finalTotal = $subtotal;
        $couponId = null;

        // 2. Validate coupon with row lock if provided
        if ($couponCode !== '') {
            $stmtCpn = $pdo->prepare('SELECT * FROM coupons WHERE coupon_code = :code FOR UPDATE');
            $stmtCpn->execute([':code' => strtoupper($couponCode)]);
            $couponRow = $stmtCpn->fetch();

            if (!$couponRow || (int)$couponRow['is_active'] !== 1) {
                throw new RuntimeException('The coupon code entered is invalid or inactive.');
            }

            $now = date('Y-m-d H:i:s');
            if ($couponRow['valid_from'] > $now || $couponRow['expiry_date'] < $now) {
                throw new RuntimeException('The coupon code has expired or is not yet valid.');
            }

            if ((int)$couponRow['usage_count'] >= (int)$couponRow['usage_limit']) {
                throw new RuntimeException('This coupon has reached its maximum usage limit.');
            }

            if ($subtotal < (float)$couponRow['minimum_order_value']) {
                throw new RuntimeException(sprintf(
                    'Order subtotal must be at least %s to apply coupon "%s".',
                    format_lkr((float)$couponRow['minimum_order_value']),
                    $couponCode
                ));
            }

            $cpnVal = (float)$couponRow['discount_value'];
            $discCents = 0;
            if ($couponRow['discount_type'] === 'percentage') {
                $discCents = (int)round(($subtotalCents * $cpnVal) / 100);
            } else {
                $discCents = (int)round($cpnVal * 100);
            }

            if ($discCents > $subtotalCents) {
                $discCents = $subtotalCents;
            }

            $discountAmount = round($discCents / 100, 2);
            $finalTotal = round(($subtotalCents - $discCents) / 100, 2);
            $couponId = (int)$couponRow['coupon_id'];

            // Increment coupon usage
            $stmtInc = $pdo->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE coupon_id = :id');
            $stmtInc->execute([':id' => $couponId]);
        }

        // 3. Generate unique order number (e.g. ORD-20261022-XXXX)
        $orderNumber = sprintf('ORD-%s-%04d', date('Ymd'), random_int(1000, 9999));

        // 4. Insert Primary Order Record
        $stmtOrder = $pdo->prepare(
            'INSERT INTO orders (order_number, user_id, idempotency_token, fulfillment_method,
                                 delivery_address, contact_phone, order_notes, subtotal,
                                 discount_amount, total_amount, coupon_id, status, created_at, updated_at)
             VALUES (:order_num, :user_id, :idemp, :fulfillment, :address, :phone, :notes,
                     :subtotal, :discount, :total, :coupon_id, :status, NOW(), NOW())'
        );

        $stmtOrder->execute([
            ':order_num'    => $orderNumber,
            ':user_id'      => $userId,
            ':idemp'        => $idempotencyToken,
            ':fulfillment'  => $fulfillmentMethod,
            ':address'      => $deliveryAddress,
            ':phone'        => $contactPhone,
            ':notes'        => $orderNotes !== '' ? $orderNotes : null,
            ':subtotal'     => $subtotal,
            ':discount'     => $discountAmount,
            ':total'        => $finalTotal,
            ':coupon_id'    => $couponId,
            ':status'       => 'Placed',
        ]);

        $orderId = (int)$pdo->lastInsertId();

        // 5. Insert Itemized Order Items
        $stmtItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, dish_id, dish_name, quantity, unit_price, line_total, created_at)
             VALUES (:order_id, :dish_id, :dish_name, :qty, :unit_price, :line_total, NOW())'
        );

        foreach ($orderItemsPayload as $item) {
            $stmtItem->execute([
                ':order_id'   => $orderId,
                ':dish_id'    => $item['dish_id'],
                ':dish_name'  => $item['dish_name'],
                ':qty'        => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':line_total' => $item['line_total'],
            ]);

            // 6. Deduct stock through Component B's approved reduce_stock() function
            // Note: reduce_stock detects $pdo->inTransaction() === true and participates in this transaction!
            reduce_stock($item['dish_id'], $item['quantity'], $userId, $pdo);
        }

        // 7. Insert Simulated Payment Record
        $simRefPrefix = match ($paymentMethod) {
            'Card'             => 'SIM-CARD-',
            'Bank Transfer'    => 'SIM-BANK-',
            default            => 'SIM-COD-',
        };
        $txReference = $simRefPrefix . strtoupper(bin2hex(random_bytes(4)));

        $paymentStatus = match ($paymentMethod) {
            'Card'          => 'Paid',      // Card simulation succeeds immediately
            'Bank Transfer' => 'Pending',   // Transfer awaits bank slip clearance
            default         => 'Pending',   // COD is pending until physical delivery
        };

        $paymentNotes = match ($paymentMethod) {
            'Card'          => 'Simulated online payment approved.',
            'Bank Transfer' => 'Simulated transfer reference recorded.',
            default         => 'Cash will be collected upon arrival.',
        };

        $stmtPay = $pdo->prepare(
            'INSERT INTO payments (order_id, payment_method, amount, payment_status,
                                   transaction_reference, payment_notes, created_at, updated_at)
             VALUES (:order_id, :method, :amount, :status, :ref, :notes, NOW(), NOW())'
        );
        $stmtPay->execute([
            ':order_id' => $orderId,
            ':method'   => $paymentMethod,
            ':amount'   => $finalTotal,
            ':status'   => $paymentStatus,
            ':ref'      => $txReference,
            ':notes'    => $paymentNotes,
        ]);

        // 8. Insert Initial Order Status History
        $stmtHist = $pdo->prepare(
            'INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, notes, changed_at)
             VALUES (:order_id, NULL, :status, :user_id, :notes, NOW())'
        );
        $stmtHist->execute([
            ':order_id' => $orderId,
            ':status'   => 'Placed',
            ':user_id'  => $userId,
            ':notes'    => sprintf('Order placed via checkout with %s (%s)', $paymentMethod, $fulfillmentMethod),
        ]);

        // 9. COMMIT TRANSACTION
        $pdo->commit();

        // 10. Audit logging and Cart cleanup
        log_action(
            'order_placed',
            sprintf('Order #%s created (Total: %s, Method: %s)', $orderNumber, format_lkr($finalTotal), $fulfillmentMethod),
            $userId,
            $pdo
        );

        cart_clear();

        return [
            'success'      => true,
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'error'        => null,
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(sprintf('[Checkout Error] User %d failed checkout: %s', $userId, $e->getMessage()));

        return [
            'success'      => false,
            'order_id'     => null,
            'order_number' => null,
            'error'        => $e->getMessage(),
        ];
    }
}

// =============================================================================
// 4. ORDER STATUS STATE MACHINE & TRANSITION RULES
// =============================================================================

/**
 * Valid state transition maps according to the assignment requirements.
 */
const STATUS_TRANSITIONS_DELIVERY = [
    'Placed'           => ['Preparing', 'Cancelled'],
    'Preparing'        => ['Ready', 'Cancelled'],
    'Ready'            => ['Out for Delivery'],
    'Out for Delivery' => ['Completed'],
    'Completed'        => [],
    'Cancelled'        => [],
];

const STATUS_TRANSITIONS_PICKUP = [
    'Placed'     => ['Preparing', 'Cancelled'],
    'Preparing'  => ['Ready', 'Cancelled'],
    'Ready'      => ['Collected'],
    'Collected'  => ['Completed'],
    'Completed'  => [],
    'Cancelled'  => [],
];

/**
 * Check whether a requested status transition is legally permitted.
 *
 * @param string $currentStatus
 * @param string $targetStatus
 * @param string $fulfillmentMethod 'delivery' or 'pickup'
 * @return bool
 */
function can_transition_order(string $currentStatus, string $targetStatus, string $fulfillmentMethod): bool
{
    $map = ($fulfillmentMethod === 'pickup') ? STATUS_TRANSITIONS_PICKUP : STATUS_TRANSITIONS_DELIVERY;
    $allowedTargets = $map[$currentStatus] ?? [];
    return in_array($targetStatus, $allowedTargets, true);
}

/**
 * Get all valid next target statuses for an order.
 *
 * @param string $currentStatus
 * @param string $fulfillmentMethod
 * @return array<int, string>
 */
function get_allowed_next_statuses(string $currentStatus, string $fulfillmentMethod): array
{
    $map = ($fulfillmentMethod === 'pickup') ? STATUS_TRANSITIONS_PICKUP : STATUS_TRANSITIONS_DELIVERY;
    return $map[$currentStatus] ?? [];
}

/**
 * Advance an order to a new stage in a concurrency-safe transaction.
 * Also restores inventory via Component B's `adjust_stock()` if order is cancelled.
 *
 * @param int         $orderId
 * @param string      $newStatus
 * @param string|null $notes
 * @param int|null    $changedBy User ID of staff or customer
 * @param PDO|null    $pdo
 * @return array{success: bool, error: ?string}
 */
function transition_order_status(
    int $orderId,
    string $newStatus,
    ?string $notes = null,
    ?int $changedBy = null,
    ?PDO $pdo = null
): array {
    if ($orderId <= 0) {
        return ['success' => false, 'error' => 'Invalid order ID.'];
    }

    $pdo = $pdo ?? get_db_connection();
    $pdo->beginTransaction();

    try {
        // 1. Fetch & lock order row
        $stmtOrder = $pdo->prepare(
            'SELECT order_id, order_number, user_id, fulfillment_method, status
             FROM orders
             WHERE order_id = :id
             FOR UPDATE'
        );
        $stmtOrder->execute([':id' => $orderId]);
        $order = $stmtOrder->fetch();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $currentStatus = (string)$order['status'];
        $fulfillment = (string)$order['fulfillment_method'];
        $orderNumber = (string)$order['order_number'];

        if ($currentStatus === $newStatus) {
            $pdo->commit();
            return ['success' => true, 'error' => null];
        }

        if (!can_transition_order($currentStatus, $newStatus, $fulfillment)) {
            throw new RuntimeException(sprintf(
                'Invalid status transition from "%s" to "%s" for %s order.',
                $currentStatus,
                $newStatus,
                $fulfillment
            ));
        }

        // 2. Cancellation and Stock Restoration Policy:
        // If order transitions to 'Cancelled', restore deducted portions back to inventory
        if ($newStatus === 'Cancelled') {
            $stmtItems = $pdo->prepare('SELECT dish_id, quantity FROM order_items WHERE order_id = :id');
            $stmtItems->execute([':id' => $orderId]);
            $items = $stmtItems->fetchAll();

            foreach ($items as $item) {
                // Call adjust_stock() with movement_type 'Restock'
                adjust_stock(
                    (int)$item['dish_id'],
                    (int)$item['quantity'],
                    'Restock',
                    sprintf('Inventory restoration for cancelled Order #%s', $orderNumber),
                    $changedBy,
                    $pdo
                );
            }

            // Update payment record if applicable
            $stmtPay = $pdo->prepare(
                'UPDATE payments
                 SET payment_status = CASE WHEN payment_status = "Paid" THEN "Refunded" ELSE "Failed" END,
                     updated_at = NOW()
                 WHERE order_id = :id'
            );
            $stmtPay->execute([':id' => $orderId]);
        }

        // If transitioning to Completed and payment was Pending COD, mark as Paid
        if ($newStatus === 'Completed') {
            $stmtPayCod = $pdo->prepare(
                'UPDATE payments
                 SET payment_status = "Paid", updated_at = NOW()
                 WHERE order_id = :id AND payment_status = "Pending"'
            );
            $stmtPayCod->execute([':id' => $orderId]);
        }

        // 3. Update orders table
        $stmtUpd = $pdo->prepare(
            'UPDATE orders
             SET status = :status, updated_at = NOW()
             WHERE order_id = :id'
        );
        $stmtUpd->execute([
            ':status' => $newStatus,
            ':id'     => $orderId,
        ]);

        // 4. Record in order_status_history
        $stmtHist = $pdo->prepare(
            'INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by, notes, changed_at)
             VALUES (:order_id, :prev, :new, :by, :notes, NOW())'
        );
        $stmtHist->execute([
            ':order_id' => $orderId,
            ':prev'     => $currentStatus,
            ':new'      => $newStatus,
            ':by'       => $changedBy,
            ':notes'    => $notes ?: sprintf('Status progressed to %s', $newStatus),
        ]);

        $pdo->commit();

        log_action(
            'order_status_change',
            sprintf('Order #%s transitioned from %s to %s', $orderNumber, $currentStatus, $newStatus),
            $changedBy,
            $pdo
        );

        return ['success' => true, 'error' => null];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// =============================================================================
// 5. ORDER QUERY & AUTHORIZATION HELPERS
// =============================================================================

/**
 * Retrieve complete order details by order_id with strict ownership check.
 * Customers can only access their own orders; staff/admin have authorized access.
 *
 * @param int      $orderId
 * @param int|null $userId If provided, enforces ownership unless role is staff/admin
 * @param PDO|null $pdo
 * @return array|null
 */
function get_order_by_id(int $orderId, ?int $userId = null, ?PDO $pdo = null): ?array
{
    if ($orderId <= 0) {
        return null;
    }

    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, u.username AS customer_username,
                c.coupon_code, c.discount_type, c.discount_value
         FROM orders o
         JOIN users u ON o.user_id = u.user_id
         LEFT JOIN coupons c ON o.coupon_id = c.coupon_id
         WHERE o.order_id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return null;
    }

    // Role-based authorization check
    if ($userId !== null) {
        $userRole = current_user_role();
        $isStaffOrAdmin = in_array($userRole, ['kitchen_staff', 'admin'], true);

        if (!$isStaffOrAdmin && (int)$order['user_id'] !== $userId) {
            // Unauthorized access attempt to someone else's order
            return null;
        }
    }

    return $order;
}

/**
 * Retrieve itemized order items for an order.
 *
 * @param int      $orderId
 * @param PDO|null $pdo
 * @return array
 */
function get_order_items(int $orderId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT oi.*, d.image_path, c.name AS category_name
         FROM order_items oi
         LEFT JOIN dishes d ON oi.dish_id = d.dish_id
         LEFT JOIN categories c ON d.category_id = c.category_id
         WHERE oi.order_id = :id
         ORDER BY oi.order_item_id ASC'
    );
    $stmt->execute([':id' => $orderId]);
    return $stmt->fetchAll();
}

/**
 * Retrieve payment details for an order.
 *
 * @param int      $orderId
 * @param PDO|null $pdo
 * @return array|null
 */
function get_order_payment(int $orderId, ?PDO $pdo = null): ?array
{
    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $pay = $stmt->fetch();
    return $pay ?: null;
}

/**
 * Retrieve the complete chronological status timeline for an order.
 *
 * @param int      $orderId
 * @param PDO|null $pdo
 * @return array
 */
function get_order_status_timeline(int $orderId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT h.*, u.username AS changed_by_name, r.role_name AS changed_by_role
         FROM order_status_history h
         LEFT JOIN users u ON h.changed_by = u.user_id
         LEFT JOIN roles r ON u.role_id = r.role_id
         WHERE h.order_id = :id
         ORDER BY h.changed_at ASC, h.history_id ASC'
    );
    $stmt->execute([':id' => $orderId]);
    return $stmt->fetchAll();
}

/**
 * Retrieve paginated order history for an authenticated customer.
 *
 * @param int         $userId
 * @param string|null $filter 'all', 'active', 'completed', 'cancelled'
 * @param int         $page
 * @param int         $perPage
 * @param PDO|null    $pdo
 * @return array{orders: array, pagination: array}
 */
function get_customer_orders(
    int $userId,
    ?string $filter = null,
    int $page = 1,
    int $perPage = 10,
    ?PDO $pdo = null
): array {
    $pdo = $pdo ?? get_db_connection();

    $where = 'WHERE o.user_id = :user_id';
    $params = [':user_id' => $userId];

    if ($filter === 'active') {
        $where .= " AND o.status IN ('Placed', 'Preparing', 'Ready', 'Out for Delivery', 'Collected')";
    } elseif ($filter === 'completed') {
        $where .= " AND o.status = 'Completed'";
    } elseif ($filter === 'cancelled') {
        $where .= " AND o.status = 'Cancelled'";
    }

    // Count query
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();

    $pag = paginate($totalRecords, $perPage, $page, base_url('orders/history.php'), $filter ? ['filter' => $filter] : []);

    // Orders query
    $sql = "SELECT o.*, p.payment_method, p.payment_status,
                   COUNT(oi.order_item_id) AS item_count
            FROM orders o
            LEFT JOIN payments p ON o.order_id = p.order_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            $where
            GROUP BY o.order_id, p.payment_method, p.payment_status
            ORDER BY o.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $pag['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pag['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    return [
        'orders'     => $orders,
        'pagination' => $pag,
    ];
}

/**
 * Retrieve orders for the Kitchen Order Management Queue.
 *
 * @param string|null $statusFilter
 * @param PDO|null    $pdo
 * @return array
 */
function get_kitchen_orders(?string $statusFilter = null, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $where = '1=1';
    $params = [];

    if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
        $where = 'o.status = :status';
        $params[':status'] = $statusFilter;
    }

    $sql = "SELECT o.*, u.full_name AS customer_name, p.payment_method, p.payment_status
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            LEFT JOIN payments p ON o.order_id = p.order_id
            WHERE $where
            ORDER BY 
                CASE o.status
                    WHEN 'Placed'           THEN 1
                    WHEN 'Preparing'        THEN 2
                    WHEN 'Ready'            THEN 3
                    WHEN 'Out for Delivery' THEN 4
                    WHEN 'Collected'        THEN 5
                    WHEN 'Completed'        THEN 6
                    WHEN 'Cancelled'        THEN 7
                    ELSE 8
                END ASC,
                o.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Attach items to each kitchen order
    if (!empty($orders)) {
        $orderIds = array_column($orders, 'order_id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmtItems = $pdo->prepare(
            "SELECT order_id, dish_name, quantity, unit_price
             FROM order_items
             WHERE order_id IN ($placeholders)
             ORDER BY order_item_id ASC"
        );
        $stmtItems->execute($orderIds);
        $allItemRows = $stmtItems->fetchAll();

        $itemMap = [];
        foreach ($allItemRows as $row) {
            $itemMap[(int)$row['order_id']][] = $row;
        }

        foreach ($orders as &$ord) {
            $ord['items'] = $itemMap[(int)$ord['order_id']] ?? [];
        }
        unset($ord);
    }

    return $orders;
}

// =============================================================================
// 6. ADMINISTRATOR SALES REPORTING ANALYTICS
// =============================================================================

/**
 * Retrieve KPI sales summary statistics for the Administrator reporting dashboard.
 *
 * @param string   $startDate 'YYYY-MM-DD'
 * @param string   $endDate   'YYYY-MM-DD'
 * @param PDO|null $pdo
 * @return array{
 *   total_orders: int,
 *   completed_orders: int,
 *   cancelled_orders: int,
 *   active_orders: int,
 *   total_sales_revenue: float,
 *   average_order_value: float,
 *   total_discount_given: float
 * }
 */
function get_sales_kpi_summary(string $startDate, string $endDate, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $start = $startDate . ' 00:00:00';
    $end = $endDate . ' 23:59:59';

    $sql = "SELECT 
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                SUM(CASE WHEN status NOT IN ('Completed', 'Cancelled') THEN 1 ELSE 0 END) AS active_orders,
                SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0.00 END) AS total_sales_revenue,
                AVG(CASE WHEN status = 'Completed' THEN total_amount ELSE NULL END) AS average_order_value,
                SUM(CASE WHEN status = 'Completed' THEN discount_amount ELSE 0.00 END) AS total_discount_given
            FROM orders
            WHERE created_at BETWEEN :start AND :end";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $row = $stmt->fetch() ?: [];

    return [
        'total_orders'         => (int)($row['total_orders'] ?? 0),
        'completed_orders'     => (int)($row['completed_orders'] ?? 0),
        'cancelled_orders'     => (int)($row['cancelled_orders'] ?? 0),
        'active_orders'        => (int)($row['active_orders'] ?? 0),
        'total_sales_revenue'  => round((float)($row['total_sales_revenue'] ?? 0.00), 2),
        'average_order_value'  => round((float)($row['average_order_value'] ?? 0.00), 2),
        'total_discount_given' => round((float)($row['total_discount_given'] ?? 0.00), 2),
    ];
}

/**
 * Breakdown of orders by status within date range.
 *
 * @param string   $startDate
 * @param string   $endDate
 * @param PDO|null $pdo
 * @return array<int, array{status: string, count: int, percentage: float}>
 */
function get_orders_by_status_report(string $startDate, string $endDate, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $start = $startDate . ' 00:00:00';
    $end = $endDate . ' 23:59:59';

    $sql = "SELECT status, COUNT(*) AS count
            FROM orders
            WHERE created_at BETWEEN :start AND :end
            GROUP BY status
            ORDER BY count DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $rows = $stmt->fetchAll();

    $total = array_sum(array_column($rows, 'count'));

    $result = [];
    foreach ($rows as $r) {
        $count = (int)$r['count'];
        $result[] = [
            'status'     => (string)$r['status'],
            'count'      => $count,
            'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
        ];
    }

    return $result;
}

/**
 * Top-selling dishes calculation using INNER JOIN, GROUP BY, SUM, and ORDER BY.
 * Uses historical price at purchase time (order_items.unit_price) for accurate revenue.
 *
 * @param string   $startDate
 * @param string   $endDate
 * @param int      $limit
 * @param PDO|null $pdo
 * @return array<int, array{dish_name: string, total_quantity: int, total_revenue: float}>
 */
function get_top_selling_dishes_report(
    string $startDate,
    string $endDate,
    int $limit = 5,
    ?PDO $pdo = null
): array {
    $pdo = $pdo ?? get_db_connection();

    $start = $startDate . ' 00:00:00';
    $end = $endDate . ' 23:59:59';

    $sql = "SELECT oi.dish_name,
                   SUM(oi.quantity) AS total_quantity,
                   SUM(oi.line_total) AS total_revenue
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.order_id
            WHERE o.status = 'Completed'
              AND o.created_at BETWEEN :start AND :end
            GROUP BY oi.dish_name
            ORDER BY total_quantity DESC, total_revenue DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Sales breakdown by fulfillment method (Delivery vs Pickup).
 *
 * @param string   $startDate
 * @param string   $endDate
 * @param PDO|null $pdo
 * @return array<int, array{fulfillment_method: string, order_count: int, total_revenue: float}>
 */
function get_sales_by_fulfillment_report(string $startDate, string $endDate, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $start = $startDate . ' 00:00:00';
    $end = $endDate . ' 23:59:59';

    $sql = "SELECT fulfillment_method,
                   COUNT(*) AS order_count,
                   SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0.00 END) AS total_revenue
            FROM orders
            WHERE created_at BETWEEN :start AND :end
            GROUP BY fulfillment_method
            ORDER BY order_count DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    return $stmt->fetchAll();
}
