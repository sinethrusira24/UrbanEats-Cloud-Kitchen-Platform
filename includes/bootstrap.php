<?php
/**
 * Bootstrap file – included first by every page.
 * Loads config, starts/validates session, sets up error handling.
 */

// ── Load configuration ────────────────────────────────────────────────────
$localConfig = __DIR__ . '/../config/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
} else {
    require_once __DIR__ . '/../config/config.sample.php';
}

require_once __DIR__ . '/../config/db.php';

// ── Error handling ────────────────────────────────────────────────────────
$logFile = __DIR__ . '/../logs/error.log';

set_error_handler(function (int $severity, string $message, string $file, int $line) use ($logFile): bool {
    $entry = sprintf(
        "[%s] PHP Error [%d]: %s in %s:%d\n",
        date('Y-m-d H:i:s'), $severity, $message, $file, $line
    );
    error_log($entry, 3, $logFile);
    return true; // suppress default PHP output
});

set_exception_handler(function (Throwable $e) use ($logFile): void {
    $entry = sprintf(
        "[%s] Uncaught %s: %s in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($entry, 3, $logFile);
    http_response_code(500);
    include __DIR__ . '/errors/500.php';
    exit;
});

// ── Session ───────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME ?? 'urbaneats_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Enforce session idle timeout
$timeout = SESSION_TIMEOUT ?? 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'Your session expired. Please log in again.';
}
$_SESSION['last_activity'] = time();
