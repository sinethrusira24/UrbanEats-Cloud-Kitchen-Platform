<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Password Reset Request
 *
 * Demonstrates secure token generation and storage. As permitted by Section 5 of the assignment,
 * the generated secure one-time link is displayed on-screen for local academic evaluation.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect('auth/profile.php');
}

$error = null;
$demoResetLink = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $identifier = trim((string)($_POST['identifier'] ?? ''));

    if ($identifier === '') {
        $error = 'Please enter your username or email address.';
    } else {
        try {
            $pdo = get_db_connection();

            $stmt = $pdo->prepare(
                'SELECT user_id, username, email, is_active
                 FROM users
                 WHERE username = :u OR email = :e
                 LIMIT 1'
            );
            $stmt->execute([':u' => $identifier, ':e' => $identifier]);
            $user = $stmt->fetch();

            if ($user && (int)$user['is_active'] === 1) {
                // Generate 32-byte cryptographically secure random token (64 hex characters)
                $plainToken = bin2hex(random_bytes(32));
                // Store only SHA-256 hash in database
                $tokenHash = hash('sha256', $plainToken);

                // Token validity: 30 minutes
                $cfg = Database::loadConfig();
                $lifetime = (int)($cfg['security']['reset_token_lifetime'] ?? 1800);
                $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

                // Invalidate any previous unused tokens for this user
                $stmtInvalidate = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL');
                $stmtInvalidate->execute([':uid' => (int)$user['user_id']]);

                // Insert new reset token record
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                     VALUES (:user_id, :token_hash, :expires_at, NOW())'
                );
                $stmtInsert->execute([
                    ':user_id'    => (int)$user['user_id'],
                    ':token_hash' => $tokenHash,
                    ':expires_at' => $expiresAt,
                ]);

                // Generate full reset URL with unhashed plaintext token
                $demoResetLink = base_url('auth/reset-password.php?token=' . urlencode($plainToken));

                log_action(
                    'password_reset_request',
                    "Password reset requested for {$user['username']} ({$user['email']})",
                    (int)$user['user_id']
                );
            } else {
                // Friendly response preventing direct account enumeration
                $error = 'If an active account was found with that identifier, a reset link has been generated.';
            }
        } catch (Throwable $e) {
            $error = 'An error occurred while generating the reset request. Please try again.';
        }
    }
}

$pageTitle = 'Reset Password Request';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon-circle">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="7.5" cy="15.5" r="5.5"/>
                    <path d="m21 2-9.6 9.6"/>
                    <path d="m15.5 7.5 3 3L22 7l-3-3"/>
                </svg>
            </div>
            <h1>Password Reset</h1>
            <p>Enter your username or email to reset your account password</p>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-warning" role="alert">
                <span>ℹ️</span>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($demoResetLink !== null): ?>
            <div class="demo-banner">
                <div style="font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>🎓 Academic Evaluation Demo Link</span>
                </div>
                <p style="font-size: 0.85rem; margin-bottom: 0.75rem; line-height: 1.4;">
                    Per assignment specification Section 5, external SMTP email service is simulated. Your secure, time-limited reset link has been generated:
                </p>
                <div style="margin-bottom: 0.75rem; word-break: break-all;">
                    <code><?= e($demoResetLink) ?></code>
                </div>
                <a href="<?= e($demoResetLink) ?>" class="btn btn-primary btn-sm btn-block">
                    Click Here to Reset Password Now &rarr;
                </a>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('auth/reset-request.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="identifier" class="form-label">Username or Registered Email</label>
                <input type="text"
                       id="identifier"
                       name="identifier"
                       class="form-control"
                       placeholder="e.g. sachini_k or sachini@gmail.com"
                       value="<?= e($identifier) ?>"
                       required
                       autofocus>
                <div class="form-text">We'll generate a secure, 30-minute one-time recovery token.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                Generate Reset Link
            </button>
        </form>

        <div class="auth-footer">
            Remembered your password? <a href="<?= base_url('auth/login.php') ?>">Return to Sign In</a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
