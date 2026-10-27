<?php

declare(strict_types=1);

// Ensure the logs directory exists
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/error.log';

// Configure PHP native logging target
ini_set('log_errors', '1');
ini_set('error_log', $logFile);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/**
 * Format a detailed diagnostic message for the log.
 */
function format_error_log_entry(string $severity, string $message, string $file, int $line, ?string $trace = null): string
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
    $user = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : 'Guest';
    $timestamp = date('Y-m-d H:i:s');

    $entry = sprintf(
        "[%s] [%s] User:%s | IP:%s | %s %s\nMessage: %s in %s on line %d\n",
        $timestamp,
        strtoupper($severity),
        $user,
        $ip,
        $method,
        $uri,
        $message,
        $file,
        $line
    );

    if ($trace) {
        $entry .= "Stack Trace:\n" . $trace . "\n";
    }

    $entry .= str_repeat('-', 80) . "\n";
    return $entry;
}

/**
 * Custom PHP Error Handler
 * Converts recoverable PHP errors into logged entries and exceptions when critical.
 */
function app_error_handler(int $errno, string $errstr, string $errfile, int $line): bool
{
    // Honor error suppression operator (@)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $severityMap = [
        E_ERROR             => 'FATAL ERROR',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'PARSE ERROR',
        E_NOTICE            => 'NOTICE',
        E_CORE_ERROR        => 'CORE ERROR',
        E_CORE_WARNING      => 'CORE WARNING',
        E_COMPILE_ERROR     => 'COMPILE ERROR',
        E_COMPILE_WARNING   => 'COMPILE WARNING',
        E_USER_ERROR        => 'USER ERROR',
        E_USER_WARNING      => 'USER WARNING',
        E_USER_NOTICE       => 'USER NOTICE',
        E_DEPRECATED        => 'DEPRECATED',
        E_USER_DEPRECATED   => 'USER DEPRECATED',
    ];

    $severity = $severityMap[$errno] ?? 'UNKNOWN ERROR';
    $entry = format_error_log_entry($severity, $errstr, $errfile, $line);

    global $logFile;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

    if ($errno === E_USER_ERROR || $errno === E_RECOVERABLE_ERROR) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $line);
    }

    return true;
}

/**
 * Custom Exception Handler
 * Intercepts uncaught exceptions, logs technical details, and displays friendly 500 page.
 */
function app_exception_handler(Throwable $exception): void
{
    global $logFile;

    $entry = format_error_log_entry(
        'UNCAUGHT EXCEPTION: ' . get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

    // If running in CLI, output message cleanly
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "[CRITICAL ERROR] " . $exception->getMessage() . "\n");
        exit(1);
    }

    // Serve friendly 500 error page to web users
    if (!headers_sent()) {
        http_response_code(500);
    }

    $error500Path = dirname(__DIR__) . '/public/errors/500.php';
    if (file_exists($error500Path)) {
        require $error500Path;
    } else {
        echo '<!DOCTYPE html><html><head><title>500 Internal Error</title></head><body>';
        echo '<h1>500 — Service Unavailable</h1><p>An unexpected error occurred. The technical team has been notified.</p>';
        echo '</body></html>';
    }
    exit;
}

/**
 * Fatal Error Shutdown Handler
 */
function app_shutdown_handler(): void
{
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        global $logFile;
        $entry = format_error_log_entry(
            'FATAL SHUTDOWN',
            $error['message'],
            $error['file'],
            $error['line']
        );
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            http_response_code(500);
            $error500Path = dirname(__DIR__) . '/public/errors/500.php';
            if (file_exists($error500Path)) {
                require $error500Path;
            }
        }
    }
}

// Register global handlers
set_error_handler('app_error_handler');
set_exception_handler('app_exception_handler');
register_shutdown_function('app_shutdown_handler');
