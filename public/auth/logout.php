<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Secure Logout
 *
 * Enforces POST-only request with CSRF verification to protect against
 * Cross-Site Scripting / Cross-Site Request Forgery forced logouts.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Disallow GET requests for logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('info', 'Please use the sign-out button to log out of your session.');
    redirect('index.php');
}

// Verify CSRF Token
require_csrf();

if (is_logged_in()) {
    $userId = current_user_id();
    $userName = $_SESSION['user_name'] ?? 'User';
    log_action('user_logout', "User {$userName} logged out", $userId);
}

// Completely clear and destroy the authenticated session
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

// Start fresh guest session for flash feedback
@session_start();
session_regenerate_id(true);

flash('success', 'You have been successfully signed out.');
redirect('auth/login.php');
