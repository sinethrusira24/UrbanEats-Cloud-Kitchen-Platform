<?php
// Authentication and authorization guards.

declare(strict_types=1);

/**
 * Check whether the current request is from an authenticated user.
 *
 * @return bool
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

/**
 * Retrieve the current authenticated user's ID.
 *
 * @return int|null
 */
function current_user_id(): ?int
{
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Retrieve the current authenticated user's role identifier string.
 * Contract roles: 'customer', 'kitchen_staff', 'admin'
 *
 * @return string|null
 */
function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Retrieve complete user account record for the authenticated user from the database.
 * Caches record statically per request for optimal performance.
 *
 * @return array|null
 */
function current_user(): ?array
{
    static $cachedUser = null;

    if (!is_logged_in()) {
        return null;
    }

    if ($cachedUser !== null) {
        return $cachedUser;
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.role_id, u.full_name, u.username, u.email, u.is_active, u.created_at, u.updated_at,
                    r.role_name, r.role_description
             FROM users u
             INNER JOIN roles r ON u.role_id = r.role_id
             WHERE u.user_id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => current_user_id()]);
        $cachedUser = $stmt->fetch() ?: null;
        return $cachedUser;
    } catch (Throwable $e) {
        error_log('Failed fetching current_user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check whether the active user has one of the specified roles.
 *
 * @param string|array<int, string> $allowedRoles
 * @return bool
 */
function has_role(string|array $allowedRoles): bool
{
    $currentRole = current_user_role();
    if ($currentRole === null) {
        return false;
    }

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    return in_array($currentRole, $allowedRoles, true);
}

// Enforces that a valid authenticated session exists.
function require_login(): void
{
    if (!is_logged_in()) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }

        flash('info', 'Please log in to access this page.');
        redirect('auth/login.php');
    }
}

// Enforces authentication and checks user role permissions.
function require_role(string|array $allowedRoles): void
{
    require_login();

    if (!has_role($allowedRoles)) {
        http_response_code(403);

        $attemptedUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        log_action('unauthorized_access_attempt', sprintf('User attempted to access restricted route: %s', $attemptedUri));

        $error403Path = dirname(__DIR__) . '/public/errors/403.php';
        if (file_exists($error403Path)) {
            require $error403Path;
        } else {
            echo '<!DOCTYPE html><html><head><title>403 Access Denied</title></head><body>';
            echo '<h1>403 — Access Denied</h1><p>You do not have permission to view this resource.</p>';
            echo '</body></html>';
        }
        exit;
    }
}

// Checks if an identifier or IP is throttled due to failed login attempts.
function check_login_throttling(
    string $identifier,
    PDO $pdo,
    int $maxAttempts = 5,
    int $lockoutSeconds = 900
): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Find the timestamp of the most recent successful login for this identifier or IP
    $stmtSuccess = $pdo->prepare(
        'SELECT MAX(attempted_at) AS last_success
         FROM login_attempts
         WHERE (identifier = :identifier OR ip_address = :ip)
           AND is_success = 1'
    );
    $stmtSuccess->execute([
        ':identifier' => $identifier,
        ':ip'         => $ip,
    ]);
    $lastSuccess = $stmtSuccess->fetchColumn() ?: '1970-01-01 00:00:00';

    // Count failed attempts since the last success and within the lockout window
    $cutoff = date('Y-m-d H:i:s', time() - $lockoutSeconds);
    $effectiveStartTime = ($lastSuccess > $cutoff) ? $lastSuccess : $cutoff;

    $stmtFailures = $pdo->prepare(
        'SELECT COUNT(*) AS failure_count, MAX(attempted_at) AS last_failure
         FROM login_attempts
         WHERE (identifier = :identifier OR ip_address = :ip)
           AND is_success = 0
           AND attempted_at > :start_time'
    );
    $stmtFailures->execute([
        ':identifier' => $identifier,
        ':ip'         => $ip,
        ':start_time' => $effectiveStartTime,
    ]);
    $failRow = $stmtFailures->fetch() ?: ['failure_count' => 0, 'last_failure' => null];

    $failureCount = (int)$failRow['failure_count'];
    $lastFailure = $failRow['last_failure'];

    if ($failureCount >= $maxAttempts && $lastFailure !== null) {
        $lastFailureTime = strtotime($lastFailure);
        $elapsed = time() - $lastFailureTime;
        $remaining = $lockoutSeconds - $elapsed;

        if ($remaining > 0) {
            return [
                'throttled'         => true,
                'remaining_seconds' => $remaining,
                'failed_attempts'   => $failureCount,
            ];
        }
    }

    return [
        'throttled'         => false,
        'remaining_seconds' => 0,
        'failed_attempts'   => $failureCount,
    ];
}

// Records a login attempt into the login_attempts table.
function record_login_attempt(string $identifier, bool $isSuccess, PDO $pdo): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier, ip_address, is_success, attempted_at)
         VALUES (:identifier, :ip, :is_success, NOW())'
    );
    $stmt->execute([
        ':identifier' => substr($identifier, 0, 120),
        ':ip'         => substr($ip, 0, 45),
        ':is_success' => $isSuccess ? 1 : 0,
    ]);
}
