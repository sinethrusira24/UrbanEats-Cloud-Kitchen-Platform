<?php
/**
 * auth.php – Authentication & authorisation guard.
 *
 * Provides:
 *   require_login()       – redirect guests to login
 *   require_role($role)   – redirect unauthorised users to access-denied
 *   current_user()        – return session user array or null
 *   is_logged_in()        – boolean check
 *   has_role($role)       – boolean role check
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Return the currently logged-in user array or null.
 */
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id'   => (int)$_SESSION['user_id'],
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role']      ?? '',
        'role_id'   => (int)($_SESSION['role_id'] ?? 0),
        'email'     => $_SESSION['email']     ?? '',
    ];
}

/**
 * Is a user currently logged in?
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Does the logged-in user have a specific role?
 *
 * @param string|string[] $role  Single role name or array of allowed roles.
 */
function has_role(string|array $role): bool {
    if (!is_logged_in()) {
        return false;
    }
    $userRole = $_SESSION['role'] ?? '';
    if (is_array($role)) {
        return in_array($userRole, $role, true);
    }
    return $userRole === $role;
}

/**
 * Redirect to login if not authenticated.
 * Saves the intended URL so the user is returned there after login.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . app_url('/auth/login.php'));
        exit;
    }
}

/**
 * Require a specific role (or one of several roles).
 * Calls require_login() first, then checks the role.
 *
 * @param string|string[] $role
 */
function require_role(string|array $role): void {
    require_login();
    if (!has_role($role)) {
        http_response_code(403);
        include __DIR__ . '/errors/403.php';
        exit;
    }
}

/**
 * Build an absolute URL relative to the public root.
 */
function app_url(string $path = ''): string {
    $base = rtrim(APP_URL ?? 'http://localhost/UrbanEats-Cloud-Kitchen-Platform/public', '/');
    return $base . '/' . ltrim($path, '/');
}
