<?php
/**
 * logout.php – Destroy the session and redirect to login.
 * Only accepts POST (with CSRF token) to prevent CSRF-logout attacks.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (is_logged_in()) {
        $user = current_user();
        log_action('user_logout', "User {$user['username']} signed out.", $user['user_id']);
    }

    // Completely destroy the session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    // Start a fresh session for the flash message
    session_start();
    flash('success', 'You have been signed out successfully.');
}

redirect(app_url('/auth/login.php'));
