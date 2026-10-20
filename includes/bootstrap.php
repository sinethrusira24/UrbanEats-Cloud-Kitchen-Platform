<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Identity, Access and Administration
 *
 * Central Application Bootstrap
 * Initializes security, error handling, session hardening, and core includes.
 */

declare(strict_types=1);

// Set default timezone
date_default_timezone_set('Asia/Colombo');

// Load Error & Exception Handling
require_once __DIR__ . '/errors.php';

// Load Database Connection & Configuration
require_once dirname(__DIR__) . '/config/db.php';

// Load Core Helper Functions
require_once __DIR__ . '/functions.php';

// Load CSRF Protection Utilities
require_once __DIR__ . '/csrf.php';

// Load Authentication and Authorization Guards
require_once __DIR__ . '/auth.php';

// -----------------------------------------------------------------------------
// Secure Session Hardening
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,               // Expire on browser close
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,        // True if HTTPS is enabled
        'httponly' => true,            // Mitigates cookie theft via XSS
        'samesite' => 'Lax',           // CSRF mitigation
    ]);

    session_start();
}

// -----------------------------------------------------------------------------
// Session Inactivity Timeout
// -----------------------------------------------------------------------------
$sessionLifetime = 1800; // 30 minutes
try {
    $cfg = Database::loadConfig();
    $sessionLifetime = (int)($cfg['security']['session_lifetime'] ?? 1800);
} catch (Throwable) {
    // Fallback to 1800
}

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionLifetime)) {
        // Session expired due to inactivity
        $userId = (int)$_SESSION['user_id'];
        log_action('session_timeout', 'User session expired due to inactivity', $userId);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        @session_destroy();
        @session_start();

        flash('warning', 'Your session has expired due to inactivity. Please sign in again.');
        redirect('auth/login.php');
    }

    $_SESSION['last_activity'] = time();

    // Verify account active status periodically to catch administrative deactivation
    if (!isset($_SESSION['status_checked_at']) || (time() - $_SESSION['status_checked_at'] > 60)) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SELECT is_active, role_id FROM users WHERE user_id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$_SESSION['user_id']]);
            $userRow = $stmt->fetch();

            if (!$userRow || (int)$userRow['is_active'] !== 1) {
                // User account has been deactivated or deleted
                $deactivatedId = (int)$_SESSION['user_id'];
                log_action('account_locked_out', 'Active session terminated because account is deactivated', $deactivatedId);

                $_SESSION = [];
                @session_destroy();
                @session_start();

                flash('danger', 'Your account has been deactivated. Please contact an administrator.');
                redirect('auth/login.php');
            }

            // Sync role in case admin modified user role
            $stmtRole = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :role_id LIMIT 1');
            $stmtRole->execute([':role_id' => $userRow['role_id']]);
            $roleRow = $stmtRole->fetch();
            if ($roleRow && !empty($roleRow['role_name'])) {
                $_SESSION['role'] = $roleRow['role_name'];
            }

            $_SESSION['status_checked_at'] = time();
        } catch (Throwable) {
            // Do not break execution if DB check temporarily fails
        }
    }
}
