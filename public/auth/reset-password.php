<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Password Reset Submission
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect('auth/profile.php');
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = false;
$resetRecord = null;
$error = null;
$errors = [];

if ($token === '') {
    $error = 'Missing password reset token. Please request a new link.';
} else {
    try {
        $pdo = get_db_connection();
        $tokenHash = hash('sha256', $token);

        // Check if token exists, has not been used, has not expired, and user is active
        $stmt = $pdo->prepare(
            'SELECT pr.reset_id, pr.user_id, pr.expires_at, pr.used_at,
                    u.username, u.email, u.is_active
             FROM password_resets pr
             INNER JOIN users u ON pr.user_id = u.user_id
             WHERE pr.token_hash = :hash
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $resetRecord = $stmt->fetch();

        if (!$resetRecord) {
            $error = 'This password reset link is invalid. Please request a new one.';
        } elseif ($resetRecord['used_at'] !== null) {
            $error = 'This password reset link has already been used. Please request a new link.';
        } elseif (strtotime($resetRecord['expires_at']) < time()) {
            $error = 'This password reset link has expired (valid for 30 minutes). Please request a new one.';
        } elseif ((int)$resetRecord['is_active'] !== 1) {
            $error = 'This account has been deactivated. Please contact an administrator.';
        } else {
            $tokenValid = true;
        }
    } catch (Throwable $e) {
        $error = 'Database verification error. Please try again.';
    }
}

// Process Password Reset Submission
if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $pwdErrors = validate_password_strength($newPassword);
    if (!empty($pwdErrors)) {
        $errors['new_password'] = implode(' ', $pwdErrors);
    }

    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            // Transactional update to guarantee atomicity of password update and token consumption
            $pdo->beginTransaction();

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $userId = (int)$resetRecord['user_id'];
            $resetId = (int)$resetRecord['reset_id'];

            // 1. Update user password
            $stmtUser = $pdo->prepare(
                'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE user_id = :id'
            );
            $stmtUser->execute([':hash' => $newHash, ':id' => $userId]);

            // 2. Mark this token as used
            $stmtToken = $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE reset_id = :id'
            );
            $stmtToken->execute([':id' => $resetId]);

            // 3. Invalidate any other pending tokens for this user
            $stmtOther = $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL'
            );
            $stmtOther->execute([':uid' => $userId]);

            $pdo->commit();

            log_action(
                'password_reset_complete',
                "Password successfully reset via secure token for user {$resetRecord['username']}",
                $userId
            );

            flash('success', 'Your password has been reset successfully! Please sign in with your new password.');
            redirect('auth/login.php');
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'Failed to reset password. Please try again.';
        }
    }
}

$pageTitle = 'Set New Password';
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
            <h1>Set New Password</h1>
            <?php if ($resetRecord && $tokenValid): ?>
                <p>Creating new password for <strong><?= e($resetRecord['username']) ?></strong></p>
            <?php else: ?>
                <p>Password Reset Verification</p>
            <?php endif; ?>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠️</span>
                <div><?= e($error) ?></div>
            </div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="<?= base_url('auth/reset-request.php') ?>" class="btn btn-primary">
                    Request a New Reset Link
                </a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <span>⚠️</span>
                    <div><?= e($errors['general']) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/reset-password.php') ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password"
                           id="new_password"
                           name="new_password"
                           class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                           placeholder="••••••••"
                           autocomplete="new-password"
                           required
                           autofocus>
                    <div class="form-text">Min 8 chars with uppercase, lowercase, number & special character.</div>
                    <?php if (isset($errors['new_password'])): ?>
                        <div class="invalid-feedback"><?= e($errors['new_password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                           placeholder="••••••••"
                           autocomplete="new-password"
                           required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>

        <div class="auth-footer">
            Remember your credentials? <a href="<?= base_url('auth/login.php') ?>">Return to Sign In</a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
