<?php

declare(strict_types=1);

/**
 * Escape a string for safe HTML output (mitigates Cross-Site Scripting / XSS).
 *
 * @param string|int|float|null $value
 * @return string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Set a one-time flash message in the session.
 *
 * @param string $type Message category: 'success', 'danger', 'warning', 'info'
 * @param string $message User-facing message text
 */
function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear all pending flash messages from the session.
 *
 * @return array<int, array{type: string, message: string}>
 */
function get_flash_messages(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

/**
 * Determine the base public URL of the application dynamically or from config.
 *
 * @param string $path Optional path to append
 * @return string Full URL
 */
function base_url(string $path = ''): string
{
    static $cachedBase = null;

    if ($cachedBase === null) {
        // Check config first
        if (class_exists('Database')) {
            try {
                $config = Database::loadConfig();
                if (!empty($config['app']['url'])) {
                    $cachedBase = rtrim($config['app']['url'], '/');
                }
            } catch (Throwable) {
                // Fallback to dynamic detection
            }
        }

        if ($cachedBase === null) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            // Derive public folder relative path
            $pos = strpos($scriptName, '/public');
            if ($pos !== false) {
                $baseSubdir = substr($scriptName, 0, $pos + 7);
                $cachedBase = "{$protocol}://{$host}{$baseSubdir}";
            } else {
                $cachedBase = "{$protocol}://{$host}/UrbanEats-Cloud-Kitchen-Platform/public";
            }
        }
    }

    $path = ltrim($path, '/');
    return $path === '' ? $cachedBase : "{$cachedBase}/{$path}";
}

/**
 * Safe redirect to a relative or absolute URL.
 *
 * @param string $url Target path or absolute URL
 */
function redirect(string $url): void
{
    // If not an absolute URL, prepend base_url
    if (!preg_match('#^https?://#i', $url)) {
        $url = base_url($url);
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    }
    exit;
}

// Records security, administrative, and domain events into the audit log.
function log_action(string $action, ?string $details = null, ?int $user_id = null, ?PDO $pdo = null): void
{
    try {
        if ($pdo === null) {
            if (!function_exists('get_db_connection')) {
                require_once dirname(__DIR__) . '/config/db.php';
            }
            $pdo = get_db_connection();
        }

        // Auto-resolve user_id from session if not explicitly provided
        if ($user_id === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $user_id = (int)$_SESSION['user_id'];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, details, ip_address, user_agent, created_at)
             VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())'
        );

        $stmt->bindValue(':user_id', $user_id, $user_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':action', substr($action, 0, 100), PDO::PARAM_STR);
        $stmt->bindValue(':details', $details, $details !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ip_address', substr($ip, 0, 45), PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);

        $stmt->execute();
    } catch (Throwable $e) {
        // Failure to write to audit log should be recorded to file, not crash the main application
        error_log(sprintf(
            '[%s] Audit log failure for action "%s": %s',
            date('Y-m-d H:i:s'),
            $action,
            $e->getMessage()
        ));
    }
}

// Validates password against length and character requirements.
function validate_password_strength(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter (a-z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number (0-9).';
    }
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&* etc.).';
    }

    return $errors;
}

// Pagination calculation helper.
function paginate(int $totalRecords, int $perPage, int $currentPage, string $baseUrl, array $queryParams = []): array
{
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    $buildPageUrl = function (int $page) use ($baseUrl, $queryParams): string {
        $params = array_merge($queryParams, ['page' => $page]);
        return $baseUrl . '?' . http_build_query($params);
    };

    return [
        'total_records' => $totalRecords,
        'per_page'      => $perPage,
        'total_pages'   => $totalPages,
        'current_page'  => $currentPage,
        'offset'        => $offset,
        'has_prev'      => $currentPage > 1,
        'has_next'      => $currentPage < $totalPages,
        'prev_url'      => $currentPage > 1 ? $buildPageUrl($currentPage - 1) : null,
        'next_url'      => $currentPage < $totalPages ? $buildPageUrl($currentPage + 1) : null,
        'build_url'     => $buildPageUrl,
    ];
}
