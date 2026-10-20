<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu, Catalogue and Inventory
 *
 * Component B Integration Gateway & Module Helper
 *
 * This file provides the standardized external API for Component C (Orders & Checkout)
 * and shared layouts to interact with Component B services.
 */

declare(strict_types=1);

require_once __DIR__ . '/stock.php';

/**
 * Fetch all active categories with their active dish counts.
 *
 * @param PDO|null $pdo
 * @return array<int, array{category_id: int, name: string, description: string, dish_count: int}>
 */
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

/**
 * Fetch complete dish record including category and current inventory status.
 *
 * @param int      $dish_id
 * @param PDO|null $pdo
 * @return array|null
 */
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

/**
 * Check whether a dish has enough available portions ready to serve.
 * Exposed to Component C before adding items to customer cart.
 *
 * @param int      $dish_id
 * @param int      $requested_qty
 * @param PDO|null $pdo
 * @return bool True if dish is active and has sufficient portions
 */
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
