<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/functions.php';

class InsufficientStockException extends RuntimeException {}
class InvalidDishInventoryException extends InvalidArgumentException {}

// Deducts dish stock atomically during order placement.
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

// Manual staff stock adjustment (Restock, Wastage, Adjustment).
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

// Calculates KPI summary statistics for the inventory dashboard.
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

// Formats a numeric price to Sri Lankan Rupees string.
function format_lkr(float|int|string $price): string
{
    return 'LKR ' . number_format((float)$price, 2);
}

// Renders an accessible HTML status badge based on stock level.
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
