<?php
/**
 * dish-delete.php – Component B: Soft-Delete and Restore Dish Handler.
 *
 * Soft-delete implementation sets is_active = 0 instead of DELETE FROM.
 * This preserves referential integrity with past order_items in Component C.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role(['Kitchen Staff', 'Administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/menu/manage.php'));
}

csrf_verify();

$dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'soft_delete'; // 'soft_delete' | 'restore'

if (!$dishId) {
    flash('error', 'Invalid dish identifier.');
    redirect(app_url('/menu/manage.php'));
}

$db = get_db();

// Verify dish exists
$stmt = $db->prepare('SELECT name, is_active FROM dishes WHERE dish_id = :id');
$stmt->execute([':id' => $dishId]);
$dish = $stmt->fetch();

if (!$dish) {
    flash('error', 'Dish not found.');
    redirect(app_url('/menu/manage.php'));
}

$dishName = $dish['name'];

try {
    if ($action === 'restore') {
        $updateStmt = $db->prepare('UPDATE dishes SET is_active = 1 WHERE dish_id = :id');
        $updateStmt->execute([':id' => $dishId]);
        
        log_action('dish_restored', json_encode([
            'dish_id' => $dishId,
            'name'    => $dishName,
        ]));
        
        flash('success', "Dish &ldquo;{$dishName}&rdquo; was restored to active menu.");
    } else {
        // Soft delete: sets is_active = 0
        $updateStmt = $db->prepare('UPDATE dishes SET is_active = 0 WHERE dish_id = :id');
        $updateStmt->execute([':id' => $dishId]);
        
        log_action('dish_soft_deleted', json_encode([
            'dish_id' => $dishId,
            'name'    => $dishName,
            'reason'  => 'Staff soft-deleted from catalogue',
        ]));
        
        flash('warning', "Dish &ldquo;{$dishName}&rdquo; was soft-deleted. It is now hidden from customers while preserving order history.");
    }
} catch (PDOException $e) {
    error_log('[UrbanEats] Soft delete error: ' . $e->getMessage());
    flash('error', 'Failed to update dish status due to a database error.');
}

redirect(app_url('/menu/manage.php'));
