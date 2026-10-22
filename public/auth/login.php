<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Login with Brute-Force Throttling
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect(current_user_role() === 'admin' ? 'admin/index.php' : 'auth/profile.php');
}

$error = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Please enter both your username/email and password.';
    } else {
        try {
            $pdo = get_db_connection();

            // 1. Throttling Check (Max 5 consecutive failed attempts -> 15 min lockout)
            $cfg = Database::loadConfig();
            $maxAttempts    = (int)($cfg['security']['login_max_attempts'] ?? 5);
            $lockoutSeconds = (int)($cfg['security']['login_lockout_seconds'] ?? 900);

            $throttle = check_login_throttling($identifier, $pdo, $maxAttempts, $lockoutSeconds);

            if ($throttle['throttled']) {
                $minutesRemaining = (int)ceil($throttle['remaining_seconds'] / 60);
                $error = sprintf(
                    'Account access temporarily locked due to %d consecutive failed login attempts. Please try again in %d minute(s).',
                    $maxAttempts,
                    $minutesRemaining
                );
                log_action('login_locked_out', "Login blocked by rate limiter for identifier: {$identifier}");
            } else {
                // 2. Fetch User Record with Role
                $stmt = $pdo->prepare(
                    'SELECT u.user_id, u.role_id, u.full_name, u.username, u.email, u.password_hash, u.is_active,
                            r.role_name
                     FROM users u
                     INNER JOIN roles r ON u.role_id = r.role_id
                     WHERE u.username = :ident_u OR u.email = :ident_e
                     LIMIT 1'
                );
                $stmt->execute([
                    ':ident_u' => $identifier,
                    ':ident_e' => $identifier,
                ]);
                $user = $stmt->fetch();

                // 3. Verify Password (constant-time verification)
                $passwordValid = $user && password_verify($password, $user['password_hash']);

                if (!$passwordValid) {
                    // Record failure attempt
                    record_login_attempt($identifier, false, $pdo);
                    log_action('login_failed', "Failed authentication attempt for identifier: {$identifier}");

                    // Calculate remaining attempts for feedback
                    $newThrottle = check_login_throttling($identifier, $pdo, $maxAttempts, $lockoutSeconds);
                    $attemptsMade = $newThrottle['failed_attempts'];
                    $attemptsLeft = max(0, $maxAttempts - $attemptsMade);

                    if ($attemptsLeft > 0) {
                        $error = "Invalid username/email or password. {$attemptsLeft} attempt(s) remaining before temporary lockout.";
                    } else {
                        $mins = (int)ceil($lockoutSeconds / 60);
                        $error = "Invalid credentials. Maximum failed attempts exceeded. Access locked for {$mins} minutes.";
                    }
                } elseif ((int)$user['is_active'] !== 1) {
                    // Account deactivated
                    record_login_attempt($identifier, false, $pdo);
                    log_action('login_rejected_inactive', "Inactive account login rejected: {$identifier}", (int)$user['user_id']);
                    $error = 'Your account has been deactivated. Please contact an administrator for assistance.';
                } else {
                    // 4. Successful Authentication
                    record_login_attempt($identifier, true, $pdo);

                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Integration Session Contract:
                    // $_SESSION['user_id'] and $_SESSION['role']
                    $_SESSION['user_id']       = (int)$user['user_id'];
                    $_SESSION['role']          = (string)$user['role_name'];
                    $_SESSION['user_name']     = (string)$user['username'];
                    $_SESSION['last_activity'] = time();

                    log_action('user_login', "User {$user['username']} logged in successfully", (int)$user['user_id']);

                    flash('success', 'Welcome back, ' . e($user['full_name']) . '!');

                    // Redirect to intended route or default role landing page
                    if (!empty($_SESSION['redirect_after_login'])) {
                        $target = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        redirect($target);
                    }

                    if ($user['role_name'] === 'admin') {
                        redirect('admin/index.php');
                    } else {
                        redirect('auth/profile.php');
                    }
                }
            }
        } catch (Throwable $e) {
            $error = 'Authentication service is temporarily unavailable. Please try again.';
        }
    }
}

$pageTitle = 'Sign In';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon-circle">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1>Welcome Back</h1>
            <p>Sign in to your UrbanEats account</p>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠️</span>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('auth/login.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="identifier" class="form-label">Username or Email</label>
                <input type="text"
                       id="identifier"
                       name="identifier"
                       class="form-control"
                       value="<?= e($identifier) ?>"
                       autocomplete="username"
                       required
                       autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label for="password" class="form-label" style="margin-bottom: 0;">Password</label>
                    <a href="<?= base_url('auth/reset-request.php') ?>" style="font-size: 0.85rem;">Forgot Password?</a>
                </div>
                <div class="password-input-wrapper">
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control"
                           autocomplete="current-password"
                           required>
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password" tabindex="-1">
                        <svg class="eye-icon eye-show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-icon eye-hide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account yet? <a href="<?= base_url('auth/register.php') ?>">Create an account</a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
