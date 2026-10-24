<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu, Catalogue and Inventory
 *
 * Inventory Engine & Concurrency-Safe Stock Management
 *
 * Implements atomic, race-condition safe stock operations with transaction participation
 * contract for Component C (Checkout & Orders).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Custom Exception thrown when requested stock exceeds available portions.
 */
class InsufficientStockException extends RuntimeException {}

/**
 * Custom Exception thrown when an invalid dish or inventory record is specified.
 */
class InvalidDishInventoryException extends InvalidArgumentException {}

/**
 * Concurrency-Safe Stock Reduction Function
 *
 * Mandatory Integration Contract for Component C (Checkout):
 * Deducts dish stock atomically during order placement.
 *
 * Behavior:
 * 1. Validates dish ID and positive quantity.
 * 2. Confirms dish exists and is active (is_active = 1).
 * 3. Uses atomic conditional SQL update: `WHERE dish_id = :id AND qty_available >= :qty`
 * 4. Records stock movement entry with movement_type 'Checkout_Deduction'.
 * 5. Handles transaction ownership safely:
 *    - If Component C already opened a transaction ($pdo->inTransaction() === true),
 *      it participates in the existing transaction without committing or rolling back.
 *    - If called independently, it manages its own transaction.
 *
 * @param int      $dish_id  Primary key of the dish to reduce
 * @param int      $qty      Portions requested (must be positive integer >= 1)
 * @param int|null $user_id  Customer or staff user ID responsible (optional)
 * @param PDO|null $pdo      Active PDO connection (optional; reuses singleton if omitted)
 *
 * @return bool True on successful deduction; throws exception on failure.
 *
 * @throws InvalidDishInventoryException If quantity is <= 0 or dish not found / inactive.
 * @throws InsufficientStockException    If requested quantity exceeds available stock.
 * @throws PDOException                  On database communication failure.
 */
function reduce_stock(int $dish_id, int $qty, ?int $user_id = null, ?PDO $pdo = null): bool
{
    if ($dish_id <= 0) {
        throw new InvalidDishInventoryException('Dish ID must be a positive integer.');
    }

    if ($qty <= 0) {
        throw new InvalidDishInventoryException('Stock reduction quantity must be at least 1.');
    }

    $pdo = $pdo ?? get_db_connection();

    // Check if an outer transaction is already active (e.g., Component C order placement)
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Verify dish exists and is active
        $stmtDish = $pdo->prepare('SELECT dish_id, name, is_active FROM dishes WHERE dish_id = :id LIMIT 1');
        $stmtDish->execute([':id' => $dish_id]);
        $dish = $stmtDish->fetch();

        if (!$dish) {
            throw new InvalidDishInventoryException(sprintf('Dish ID #%d does not exist.', $dish_id));
        }

        if ((int)$dish['is_active'] !== 1) {
            throw new InvalidDishInventoryException(sprintf('Dish "%s" (#%d) is inactive/deactivated.', $dish['name'], $dish_id));
        }

        // 2. Fetch current inventory to obtain baseline previous_stock
        $stmtInv = $pdo->prepare('SELECT inventory_id, qty_available, reorder_level FROM inventory WHERE dish_id = :id FOR UPDATE');
        $stmtInv->execute([':id' => $dish_id]);
        $inv = $stmtInv->fetch();

        if (!$inv) {
            throw new InvalidDishInventoryException(sprintf('No inventory record configured for dish #%d.', $dish_id));
        }

        $previousStock = (int)$inv['qty_available'];

        if ($previousStock < $qty) {
            throw new InsufficientStockException(sprintf(
                'Insufficient stock for "%s". Requested: %d, Available: %d.',
                $dish['name'],
                $qty,
                $previousStock
            ));
        }

        // 3. Atomic Conditional UPDATE (Eliminates race conditions between simultaneous checkouts)
        $stmtUpdate = $pdo->prepare(
            'UPDATE inventory
             SET qty_available = qty_available - :qty_deduct
             WHERE dish_id = :id AND qty_available >= :qty_check'
        );
        $stmtUpdate->execute([
            ':qty_deduct' => $qty,
            ':id'         => $dish_id,
            ':qty_check'  => $qty,
        ]);

        if ($stmtUpdate->rowCount() !== 1) {
            // Another concurrent request reduced stock in between
            throw new InsufficientStockException(sprintf('Concurrent conflict: Insufficient stock for dish #%d.', $dish_id));
        }

        $updatedStock = $previousStock - $qty;

        // 4. Record audit trail in stock_movements
        $stmtMove = $pdo->prepare(
            'INSERT INTO stock_movements (dish_id, user_id, movement_type, change_qty, previous_stock, updated_stock, reason, moved_at)
             VALUES (:dish_id, :user_id, :type, :change_qty, :prev_stock, :upd_stock, :reason, NOW())'
        );
        $stmtMove->execute([
            ':dish_id'    => $dish_id,
            ':user_id'    => $user_id,
            ':type'       => 'Checkout_Deduction',
            ':change_qty' => -$qty,
            ':prev_stock' => $previousStock,
            ':upd_stock'  => $updatedStock,
            ':reason'     => sprintf('Order checkout deduction (%d portions)', $qty),
        ]);

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Manual Staff Stock Adjustment Function (Restock / Wastage / Correction).
 *
 * @param int         $dish_id       Dish identifier
 * @param int         $change_qty    Signed or unsigned adjustment (+ for restock, - for wastage)
 * @param string      $movement_type One of: 'Restock', 'Wastage', 'Adjustment', 'Initial'
 * @param string      $reason        Mandatory descriptive reason
 * @param int|null    $user_id       Staff member performing adjustment
 * @param PDO|null    $pdo           Database connection
 *
 * @return bool
 */
function adjust_stock(
    int $dish_id,
    int $change_qty,
    string $movement_type,
    string $reason,
    ?int $user_id = null,
    ?PDO $pdo = null
): bool {
    if ($dish_id <= 0) {
        throw new InvalidArgumentException('Dish ID must be a positive integer.');
    }

    if ($change_qty === 0) {
        throw new InvalidArgumentException('Change quantity cannot be zero.');
    }

    $allowedTypes = ['Restock', 'Wastage', 'Adjustment', 'Initial'];
    if (!in_array($movement_type, $allowedTypes, true)) {
        throw new InvalidArgumentException(sprintf('Invalid movement type "%s".', $movement_type));
    }

    $reason = trim($reason);
    if ($reason === '') {
        throw new InvalidArgumentException('A reason is mandatory for manual stock adjustments.');
    }

    $pdo = $pdo ?? get_db_connection();
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // Fetch inventory with row lock
        $stmtInv = $pdo->prepare('SELECT inventory_id, qty_available FROM inventory WHERE dish_id = :id FOR UPDATE');
        $stmtInv->execute([':id' => $dish_id]);
        $inv = $stmtInv->fetch();

        if (!$inv) {
            throw new InvalidDishInventoryException(sprintf('No inventory record found for dish #%d.', $dish_id));
        }

        $prevStock = (int)$inv['qty_available'];
        $newStock = $prevStock + $change_qty;

        if ($newStock < 0) {
            throw new InsufficientStockException(sprintf(
                'Adjustment would result in negative inventory. Current: %d, Change: %d.',
                $prevStock,
                $change_qty
            ));
        }

        // Apply update
        $stmtUpd = $pdo->prepare('UPDATE inventory SET qty_available = :new_stock WHERE dish_id = :id');
        $stmtUpd->execute([
            ':new_stock' => $newStock,
            ':id'        => $dish_id,
        ]);

        // Insert stock movement record
        $stmtMove = $pdo->prepare(
            'INSERT INTO stock_movements (dish_id, user_id, movement_type, change_qty, previous_stock, updated_stock, reason, moved_at)
             VALUES (:dish_id, :user_id, :type, :change_qty, :prev_stock, :upd_stock, :reason, NOW())'
        );
        $stmtMove->execute([
            ':dish_id'    => $dish_id,
            ':user_id'    => $user_id,
            ':type'       => $movement_type,
            ':change_qty' => $change_qty,
            ':prev_stock' => $prevStock,
            ':upd_stock'  => $newStock,
            ':reason'     => $reason,
        ]);

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Calculate KPI summary statistics for the Kitchen Staff Inventory Dashboard.
 *
 * @param PDO|null $pdo
 * @return array{total_dishes: int, in_stock: int, low_stock: int, out_of_stock: int}
 */
function get_stock_summary(?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $sql = 'SELECT 
                COUNT(d.dish_id) AS total_dishes,
                SUM(CASE WHEN COALESCE(i.qty_available, 0) > COALESCE(i.reorder_level, 5) THEN 1 ELSE 0 END) AS in_stock,
                SUM(CASE WHEN COALESCE(i.qty_available, 0) > 0 AND COALESCE(i.qty_available, 0) <= COALESCE(i.reorder_level, 5) THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN COALESCE(i.qty_available, 0) <= 0 THEN 1 ELSE 0 END) AS out_of_stock
            FROM dishes d
            LEFT JOIN inventory i ON d.dish_id = i.dish_id
            WHERE d.is_active = 1';

    $row = $pdo->query($sql)->fetch() ?: [];

    return [
        'total_dishes' => (int)($row['total_dishes'] ?? 0),
        'in_stock'     => (int)($row['in_stock'] ?? 0),
        'low_stock'    => (int)($row['low_stock'] ?? 0),
        'out_of_stock' => (int)($row['out_of_stock'] ?? 0),
    ];
}

/**
 * Format a numeric price to Sri Lankan Rupees string (e.g., "LKR 1,650.00").
 *
 * @param float|int|string $price
 * @return string
 */
function format_lkr(float|int|string $price): string
{
    return 'LKR ' . number_format((float)$price, 2);
}

/**
 * Render an accessible HTML status badge based on current stock and reorder threshold.
 *
 * @param int  $qty_available Current available quantity
 * @param int  $reorder_level Low-stock warning threshold
 * @param bool $showQuantity  Whether to display the count inside the badge
 * @return string HTML span
 */
function render_stock_badge(int $qty_available, int $reorder_level = 5, bool $showQuantity = false): string
{
    if ($qty_available <= 0) {
        return '<span class="badge badge-danger" style="background-color: #dc2626 !important; color: #ffffff !important;">Out of Stock</span>';
    }

    if ($qty_available <= $reorder_level) {
        $text = $showQuantity ? sprintf('Low Stock (%d left)', $qty_available) : 'Low Stock';
        return '<span class="badge badge-warning" style="background-color: #fed7aa !important; color: #9a3412 !important; border: 1px solid #fdba74 !important;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $text = $showQuantity ? sprintf('In Stock (%d)', $qty_available) : 'In Stock';
    return '<span class="badge badge-success" style="background-color: #16a34a !important; color: #ffffff !important;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}
