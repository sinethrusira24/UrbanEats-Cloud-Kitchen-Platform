<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Identity, Access and Administration
 *
 * Cross-Site Request Forgery (CSRF) Protection
 * Generates and validates cryptographically secure tokens on all state-changing requests.
 */

declare(strict_types=1);

/**
 * Generate or retrieve the active CSRF token for the current session.
 *
 * @return string Hex-encoded 64-character token
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Safe check to ensure session is active
        @session_start();
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden HTML input field containing the current CSRF token.
 *
 * @return string HTML input element
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify a submitted CSRF token against the session token.
 * Uses timing-attack-safe comparison (hash_equals).
 *
 * @param string|null $submittedToken The token from POST or header
 * @return bool True if valid, false otherwise
 */
function csrf_verify(?string $submittedToken = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    if ($submittedToken === null) {
        $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (!is_string($submittedToken) || $submittedToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Enforce CSRF token verification.
 * Halts execution with an HTTP 403 response if the token is invalid or missing.
 */
function require_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(403);

        // Log security violation if logger is available
        if (function_exists('log_action')) {
            log_action('csrf_failure', 'Request rejected due to invalid or missing CSRF token');
        }

        $error403Path = dirname(__DIR__) . '/public/errors/403.php';
        if (file_exists($error403Path)) {
            $customMessage = 'Invalid or expired security token. Please refresh the page and try again.';
            require $error403Path;
        } else {
            echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>';
            echo '<h1>403 — Forbidden</h1><p>Invalid security token (CSRF mismatch).</p>';
            echo '</body></html>';
        }
        exit;
    }
}
