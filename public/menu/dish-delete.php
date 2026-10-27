<?php
// Soft delete & restore mutation handler.

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

require_role(['kitchen_staff', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('menu/manage.php');
}

require_csrf();

$dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
$action = trim((string)($_POST['action'] ?? 'deactivate'));

if (!$dishId || $dishId <= 0) {
    flash('danger', 'Invalid dish identifier specified.');
    redirect('menu/manage.php');
}

$pdo = get_db_connection();

try {
    // Verify dish exists
    $stmtCheck = $pdo->prepare('SELECT dish_id, name, is_active FROM dishes WHERE dish_id = :id LIMIT 1');
    $stmtCheck->execute([':id' => $dishId]);
    $dish = $stmtCheck->fetch();

    if (!$dish) {
        flash('danger', 'The specified dish was not found in the database.');
        redirect('menu/manage.php');
    }

    $userId = current_user_id();

    if ($action === 'restore') {
        $stmtUpdate = $pdo->prepare('UPDATE dishes SET is_active = 1, updated_at = NOW() WHERE dish_id = :id');
        $stmtUpdate->execute([':id' => $dishId]);

        log_action('dish_restored', sprintf('Restored dish "%s" (ID #%d) to active catalogue', $dish['name'], $dishId), $userId, $pdo);
        flash('success', sprintf('Dish "%s" has been restored and is now visible on the public menu.', $dish['name']));
    } else {
        // Soft delete (is_active = 0)
        $stmtUpdate = $pdo->prepare('UPDATE dishes SET is_active = 0, updated_at = NOW() WHERE dish_id = :id');
        $stmtUpdate->execute([':id' => $dishId]);

        log_action('dish_deactivated', sprintf('Soft-deleted dish "%s" (ID #%d)', $dish['name'], $dishId), $userId, $pdo);
        flash('info', sprintf('Dish "%s" has been deactivated and hidden from the public menu.', $dish['name']));
    }
} catch (Throwable $e) {
    error_log('Failed mutating dish active state: ' . $e->getMessage());
    flash('danger', 'A database error occurred while updating the dish status.');
}

redirect('menu/manage.php');
