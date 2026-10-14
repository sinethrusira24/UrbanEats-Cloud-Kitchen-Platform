<?php
/**
 * functions.php – Shared utility functions used across all components.
 *
 * Includes:
 *   log_action()          – write to audit_log table (called by B & C too)
 *   flash()               – set a one-time flash message
 *   get_flash()           – retrieve and clear a flash message
 *   e()                   – htmlspecialchars shorthand
 *   redirect()            – safe redirect helper
 *   paginate()            – build pagination metadata
 *   sanitise_string()     – basic string sanitisation
 *   format_lkr()          – format a number as LKR currency
 */

require_once __DIR__ . '/bootstrap.php';

// ── Audit Log ─────────────────────────────────────────────────────────────

/**
 * Write an action to the audit_log table.
 *
 * Called by Components A, B and C for important events.
 *
 * @param string      $action   Short action label (e.g. 'user_login', 'order_placed')
 * @param string|null $details  Human-readable details (JSON or plain text)
 * @param int|null    $user_id  Override – defaults to the logged-in user
 */
function log_action(string $action, ?string $details = null, ?int $user_id = null): void {
    try {
        $db      = get_db();
        $uid     = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip      = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO audit_log (user_id, action, details, ip_address)
             VALUES (:uid, :action, :details, :ip)'
        );
        $stmt->execute([
            ':uid'     => $uid,
            ':action'  => mb_substr($action, 0, 100),
            ':details' => $details,
            ':ip'      => $ip,
        ]);
    } catch (PDOException $e) {
        // Audit failure must never crash the main request
        error_log('[UrbanEats] log_action failed: ' . $e->getMessage());
    }
}

// ── Flash messages ────────────────────────────────────────────────────────

/**
 * Set a one-time flash message stored in the session.
 *
 * @param string $type    'success' | 'error' | 'warning' | 'info'
 * @param string $message
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Retrieve and clear all flash messages of a given type.
 *
 * @param string $type
 * @return string[] Array of messages
 */
function get_flash(string $type): array {
    $messages = $_SESSION['flash'][$type] ?? [];
    unset($_SESSION['flash'][$type]);
    return $messages;
}

// ── Output helpers ────────────────────────────────────────────────────────

/**
 * Short alias for htmlspecialchars with safe defaults.
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Perform a safe redirect and exit.
 */
function redirect(string $url): void {
    // Prevent header injection
    $url = preg_replace('/[\r\n]/', '', $url);
    header('Location: ' . $url);
    exit;
}

// ── Pagination ────────────────────────────────────────────────────────────

/**
 * Build pagination metadata.
 *
 * @param int $total     Total number of rows
 * @param int $per_page  Rows per page
 * @param int $current   Current page (1-indexed)
 * @return array{total:int, per_page:int, current_page:int, last_page:int, offset:int}
 */
function paginate(int $total, int $per_page, int $current): array {
    $per_page   = max(1, $per_page);
    $last_page  = (int)ceil($total / $per_page);
    $current    = max(1, min($current, $last_page ?: 1));
    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current,
        'last_page'    => $last_page,
        'offset'       => ($current - 1) * $per_page,
    ];
}

// ── Sanitisation ──────────────────────────────────────────────────────────

/**
 * Trim and strip tags from a string.
 */
function sanitise_string(string $input): string {
    return trim(strip_tags($input));
}

// ── Currency ──────────────────────────────────────────────────────────────

/**
 * Format a numeric amount as LKR currency string.
 */
function format_lkr(float $amount): string {
    return 'LKR ' . number_format($amount, 2);
}
