<?php

declare(strict_types=1);

require_once __DIR__ . '/stock.php';

// Fetches all active categories with dish counts.
function get_active_categories(?PDO $pdo = null): array
{
    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->query(
        'SELECT c.category_id, c.name, c.description, c.display_order,
                COUNT(d.dish_id) AS dish_count
         FROM categories c
         LEFT JOIN dishes d ON c.category_id = d.category_id AND d.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.category_id, c.name, c.description, c.display_order
         ORDER BY c.display_order ASC, c.name ASC'
    );

    return $stmt->fetchAll();
}

// Fetches complete dish record with category and inventory.
function get_dish_details(int $dish_id, ?PDO $pdo = null): ?array
{
    if ($dish_id <= 0) {
        return null;
    }

    $pdo = $pdo ?? get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT d.dish_id, d.category_id, d.name, d.description, d.price, d.image_path, d.is_active,
                d.created_at, d.updated_at,
                c.name AS category_name,
                COALESCE(i.qty_available, 0) AS qty_available,
                COALESCE(i.reorder_level, 5) AS reorder_level
         FROM dishes d
         JOIN categories c ON d.category_id = c.category_id
         LEFT JOIN inventory i ON d.dish_id = i.dish_id
         WHERE d.dish_id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $dish_id]);
    $dish = $stmt->fetch();

    return $dish ?: null;
}

// Checks whether a dish has enough available portions ready to serve.
function check_dish_availability(int $dish_id, int $requested_qty = 1, ?PDO $pdo = null): bool
{
    if ($dish_id <= 0 || $requested_qty <= 0) {
        return false;
    }

    $dish = get_dish_details($dish_id, $pdo);
    if (!$dish || (int)$dish['is_active'] !== 1) {
        return false;
    }

    return (int)$dish['qty_available'] >= $requested_qty;
}
