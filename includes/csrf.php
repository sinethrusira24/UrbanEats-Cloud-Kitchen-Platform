<?php
/**
 * csrf.php – CSRF token helpers.
 *
 * Usage in a form:
 *   <?= csrf_field() ?>
 *
 * Usage in form handler:
 *   csrf_verify();
 */

/**
 * Generate (or retrieve) the CSRF token for this session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the submitted CSRF token. Aborts with 403 on failure.
 */
function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        include __DIR__ . '/errors/403.php';
        exit;
    }
}
