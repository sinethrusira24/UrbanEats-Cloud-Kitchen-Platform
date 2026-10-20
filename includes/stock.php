<?php
/**
 * stock.php – Component B: Stock management & inventory control functions.
 *
 * Provides:
 *   reduce_stock($dish_id, $qty)         – transaction-safe stock deduction for Component C
 *   adjust_stock($dish_id, $qty, $reason)– restock / wastage adjustments
 *   set_reorder_level($dish_id, $level)  – update alert threshold
 *   get_dish($dish_id, $active_only)     – fetch dish with inventory & category info
 *   render_stock_badge($qty, $reorder)   – formatted stock status badge
 *   handle_dish_image_upload($file, $cur)– secure file upload with MIME & size validation
 *   get_stock_summary()                  – KPI metrics for stock dashboard
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions.php';

/**
 * Custom Exception for stock errors
 */
class InsufficientStockException extends RuntimeException {}

/**
 * Transaction-safe function to reduce stock at checkout.
 * Called by Component C at checkout.
 * Refuses to go below zero using atomic conditional update.
 *
 * @param int      $dish_id  The dish ID to deduct
 * @param int      $qty      Quantity to deduct (must be > 0)
 * @param int|null $user_id  Optional user ID performing the checkout
 * @return bool True if deduction succeeded
 * @throws InvalidArgumentException If $qty <= 0
 * @throws InsufficientStockException If available stock is less than $qty
 */
function reduce_stock(int $dish_id, int $qty, ?int $user_id = null): bool {
    if ($qty <= 0) {
        throw new InvalidArgumentException("Quantity to reduce must be a positive integer, {$qty} given.");
    }

    $db = get_db();
    $manageTransaction = !$db->inTransaction();

    if ($manageTransaction) {
        $db->beginTransaction();
    }

    try {
        // Atomic conditional UPDATE: only succeeds if qty_available >= $qty
        $stmt = $db->prepare(
            'UPDATE inventory 
             SET qty_available = qty_available - :deduct_qty 
             WHERE dish_id = :dish_id AND qty_available >= :min_qty'
        );
        $stmt->execute([
            ':deduct_qty' => $qty,
            ':dish_id'    => $dish_id,
            ':min_qty'    => $qty,
        ]);

        if ($stmt->rowCount() === 0) {
            // Check whether dish exists or is out of stock
            $checkStmt = $db->prepare('SELECT qty_available FROM inventory WHERE dish_id = :dish_id');
            $checkStmt->execute([':dish_id' => $dish_id]);
            $current = $checkStmt->fetchColumn();

            $available = ($current === false) ? 0 : (int)$current;
            throw new InsufficientStockException(
                "Insufficient stock for dish ID {$dish_id}. Requested: {$qty}, Available: {$available}"
            );
        }

        // Record stock movement (negative change for checkout usage)
        $movementStmt = $db->prepare(
            'INSERT INTO stock_movements (dish_id, user_id, change_qty, reason)
             VALUES (:dish_id, :user_id, :change_qty, :reason)'
        );
        $movementStmt->execute([
            ':dish_id'    => $dish_id,
            ':user_id'    => $user_id ?? ($_SESSION['user_id'] ?? null),
            ':change_qty' => -$qty,
            ':reason'     => 'Order checkout deduction',
        ]);

        // Audit log entry
        log_action('stock_deducted', json_encode([
            'dish_id'  => $dish_id,
            'deducted' => $qty,
            'reason'   => 'Order checkout'
        ]), $user_id);

        if ($manageTransaction) {
            $db->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($manageTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/**
 * Adjust stock for a dish (Restock, Wastage, Spoilage, Manual Recount).
 *
 * @param int         $dish_id    The dish ID
 * @param int         $change_qty Positive for restock, negative for wastage/deduction
 * @param string      $reason     Reason for adjustment
 * @param int|null    $user_id    Staff user ID who made the adjustment
 * @return bool
 * @throws InsufficientStockException If deduction would cause stock to fall below zero
 */
function adjust_stock(int $dish_id, int $change_qty, string $reason, ?int $user_id = null): bool {
    if ($change_qty === 0) {
        return true;
    }

    $db = get_db();
    $manageTransaction = !$db->inTransaction();

    if ($manageTransaction) {
        $db->beginTransaction();
    }

    try {
        if ($change_qty < 0) {
            // Deducting stock – enforce non-negative constraint
            $absQty = abs($change_qty);
            $stmt = $db->prepare(
                'UPDATE inventory 
                 SET qty_available = qty_available - :deduct_qty 
                 WHERE dish_id = :dish_id AND qty_available >= :min_qty'
            );
            $stmt->execute([
                ':deduct_qty' => $absQty,
                ':dish_id'    => $dish_id,
                ':min_qty'    => $absQty,
            ]);

            if ($stmt->rowCount() === 0) {
                $check = $db->prepare('SELECT qty_available FROM inventory WHERE dish_id = :dish_id');
                $check->execute([':dish_id' => $dish_id]);
                $current = (int)($check->fetchColumn() ?: 0);
                throw new InsufficientStockException(
                    "Cannot deduct {$absQty} items. Only {$current} currently in stock."
                );
            }
        } else {
            // Adding stock (Restock)
            $stmt = $db->prepare(
                'UPDATE inventory 
                 SET qty_available = qty_available + :qty 
                 WHERE dish_id = :dish_id'
            );
            $stmt->execute([
                ':qty'     => $change_qty,
                ':dish_id' => $dish_id,
            ]);
        }

        // Record stock movement
        $mStmt = $db->prepare(
            'INSERT INTO stock_movements (dish_id, user_id, change_qty, reason)
             VALUES (:dish_id, :user_id, :change_qty, :reason)'
        );
        $mStmt->execute([
            ':dish_id'    => $dish_id,
            ':user_id'    => $user_id ?? ($_SESSION['user_id'] ?? null),
            ':change_qty' => $change_qty,
            ':reason'     => trim($reason),
        ]);

        log_action('stock_adjusted', json_encode([
            'dish_id'    => $dish_id,
            'change_qty' => $change_qty,
            'reason'     => $reason,
        ]), $user_id);

        if ($manageTransaction) {
            $db->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($manageTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/**
 * Update reorder level threshold for low-stock alerts.
 */
function set_reorder_level(int $dish_id, int $reorder_level): bool {
    $reorder_level = max(0, $reorder_level);
    $db = get_db();
    $stmt = $db->prepare('UPDATE inventory SET reorder_level = :reorder WHERE dish_id = :dish_id');
    $res = $stmt->execute([
        ':reorder' => $reorder_level,
        ':dish_id' => $dish_id,
    ]);

    log_action('reorder_level_updated', json_encode([
        'dish_id'       => $dish_id,
        'reorder_level' => $reorder_level,
    ]));

    return $res;
}

/**
 * Fetch a single dish with its category and inventory info.
 */
function get_dish(int $dish_id, bool $active_only = false): ?array {
    $db = get_db();
    $sql = 'SELECT d.dish_id, d.category_id, d.name, d.description, d.price, d.image_path, d.is_active,
                   d.created_at, d.updated_at,
                   c.name AS category_name, c.is_active AS category_active,
                   COALESCE(i.qty_available, 0) AS qty_available,
                   COALESCE(i.reorder_level, 5) AS reorder_level
            FROM dishes d
            JOIN categories c ON d.category_id = c.category_id
            LEFT JOIN inventory i ON d.dish_id = i.dish_id
            WHERE d.dish_id = :id';
    
    if ($active_only) {
        $sql .= ' AND d.is_active = 1 AND c.is_active = 1';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $dish_id]);
    $dish = $stmt->fetch();

    if (!$dish) {
        return null;
    }

    // Determine computed stock state
    $qty     = (int)$dish['qty_available'];
    $reorder = (int)$dish['reorder_level'];

    if ($qty <= 0) {
        $dish['stock_status'] = 'out_of_stock';
        $dish['stock_label']  = 'Out of Stock';
    } elseif ($qty <= $reorder) {
        $dish['stock_status'] = 'low_stock';
        $dish['stock_label']  = "Low Stock ({$qty} left)";
    } else {
        $dish['stock_status'] = 'in_stock';
        $dish['stock_label']  = "In Stock ({$qty})";
    }

    return $dish;
}

/**
 * Render an HTML stock status badge.
 */
function render_stock_badge(int $qty, int $reorder_level, bool $show_count = true): string {
    if ($qty <= 0) {
        return '<span class="badge badge-stock-out" title="Currently out of stock"><span class="stock-dot"></span> Out of Stock</span>';
    } elseif ($qty <= $reorder_level) {
        $label = $show_count ? "Only {$qty} left" : 'Low Stock';
        return '<span class="badge badge-stock-low" title="Stock is running low"><span class="stock-dot pulse"></span> ' . e($label) . '</span>';
    } else {
        $label = $show_count ? "In Stock ({$qty})" : 'In Stock';
        return '<span class="badge badge-stock-in" title="Item is in stock and ready"><span class="stock-dot"></span> ' . e($label) . '</span>';
    }
}

/**
 * Securely handle dish image upload.
 *
 * Performs:
 *   1. Upload error check
 *   2. File size validation (max 2MB)
 *   3. Extension whitelist validation (.jpg, .jpeg, .png, .webp)
 *   4. MIME type inspection via finfo_file
 *   5. Image integrity check via getimagesize()
 *   6. Cryptographic random renaming to prevent directory traversal and overwrite
 *   7. Storage in public/uploads/dishes/
 *   8. Deletion of previous image if updating
 *
 * @param array       $file           The $_FILES['image'] array
 * @param string|null $current_image  Current image path (if updating)
 * @return array{success:bool, path:?string, error:?string}
 */
function handle_dish_image_upload(array $file, ?string $current_image = null): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => $current_image, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds maximum upload size allowed by server.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds maximum size allowed by the form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.',
        ];
        return [
            'success' => false,
            'path'    => null,
            'error'   => $uploadErrors[$file['error']] ?? 'An error occurred during file upload.'
        ];
    }

    // Size limit check (2 MB)
    $maxSize = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : (2 * 1024 * 1024);
    if ($file['size'] > $maxSize) {
        $maxMb = round($maxSize / (1024 * 1024), 1);
        return [
            'success' => false,
            'path'    => null,
            'error'   => "The uploaded image is too large. Maximum size is {$maxMb}MB."
        ];
    }

    // Extension validation against whitelist
    $originalName = $file['name'] ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowedExtensions, true)) {
        return [
            'success' => false,
            'path'    => null,
            'error'   => 'Invalid file format. Only JPG, PNG, and WebP images are allowed.'
        ];
    }

    // MIME type check using PHP Fileinfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mimeType, $allowedMimes, true)) {
        return [
            'success' => false,
            'path'    => null,
            'error'   => "Invalid file MIME type ({$mimeType}). The file does not appear to be a genuine image."
        ];
    }

    // Image integrity inspection via getimagesize
    $imgDetails = @getimagesize($file['tmp_name']);
    if ($imgDetails === false || $imgDetails[0] <= 0 || $imgDetails[1] <= 0) {
        return [
            'success' => false,
            'path'    => null,
            'error'   => 'The file could not be verified as a valid image.'
        ];
    }

    // Target upload directory
    $uploadDir = realpath(__DIR__ . '/../public/uploads') . DIRECTORY_SEPARATOR . 'dishes' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate cryptographic random filename to avoid collisions and directory traversal
    $newFilename = 'dish_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $targetPath = $uploadDir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => false,
            'path'    => null,
            'error'   => 'Failed to save the uploaded image to the server.'
        ];
    }

    // Delete old image if updating and old file was custom upload
    if (!empty($current_image) && str_starts_with($current_image, 'uploads/dishes/')) {
        $oldFile = realpath(__DIR__ . '/../public') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $current_image);
        if (file_exists($oldFile) && is_file($oldFile) && basename($oldFile) !== $newFilename) {
            @unlink($oldFile);
        }
    }

    return [
        'success' => true,
        'path'    => 'uploads/dishes/' . $newFilename,
        'error'   => null,
    ];
}

/**
 * Retrieve inventory overview metrics for the stock dashboard.
 *
 * @return array{total:int, healthy:int, low:int, out:int}
 */
function get_stock_summary(): array {
    $db = get_db();
    $sql = 'SELECT 
              COUNT(d.dish_id) AS total_dishes,
              SUM(CASE WHEN i.qty_available <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
              SUM(CASE WHEN i.qty_available > 0 AND i.qty_available <= i.reorder_level THEN 1 ELSE 0 END) AS low_stock,
              SUM(CASE WHEN i.qty_available > i.reorder_level THEN 1 ELSE 0 END) AS healthy_stock
            FROM dishes d
            JOIN categories c ON d.category_id = c.category_id
            LEFT JOIN inventory i ON d.dish_id = i.dish_id
            WHERE d.is_active = 1 AND c.is_active = 1';
    
    $res = $db->query($sql)->fetch();
    return [
        'total'   => (int)($res['total_dishes'] ?? 0),
        'healthy' => (int)($res['healthy_stock'] ?? 0),
        'low'     => (int)($res['low_stock'] ?? 0),
        'out'     => (int)($res['out_of_stock'] ?? 0),
    ];
}
