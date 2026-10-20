<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Change Password
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Authentication Guard
require_login();

$userId = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // Validation 1: Current Password presence
    if ($currentPassword === '') {
        $errors['current_password'] = 'Please enter your current password.';
    }

    // Validation 2: New Password strength
    $pwdErrors = validate_password_strength($newPassword);
    if (!empty($pwdErrors)) {
        $errors['new_password'] = implode(' ', $pwdErrors);
    }

    // Validation 3: Confirmation match
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'New password and confirmation do not match.';
    }

    // Validation 4: Cannot be identical to current password
    if ($currentPassword !== '' && $currentPassword === $newPassword) {
        $errors['new_password'] = 'Your new password cannot be the same as your current password.';
    }

    // Database verification and update
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            // Retrieve current password hash
            $stmt = $pdo->prepare('SELECT password_hash, username FROM users WHERE user_id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $userData = $stmt->fetch();

            if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
                $errors['current_password'] = 'The current password you entered is incorrect.';
                log_action('password_change_failed', 'Incorrect current password provided during password change', $userId);
            } else {
                // Hash new password
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

                $stmtUpdate = $pdo->prepare(
                    'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE user_id = :id'
                );
                $stmtUpdate->execute([
                    ':hash' => $newHash,
                    ':id'   => $userId,
                ]);

                // Regenerate session ID
                session_regenerate_id(true);

                // Audit Log
                log_action('password_change', "Password changed for user {$userData['username']}", $userId);

                flash('success', 'Your password has been changed successfully.');
                redirect('auth/profile.php');
            }
        } catch (Throwable $e) {
            $errors['general'] = 'Failed to update password due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Change Password';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="max-width: 560px; margin: 0 auto;">
    <div class="page-header">
        <div class="page-title">
            <h1>Change Password</h1>
            <p class="page-subtitle">Update your account credentials to keep your profile secure</p>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" role="alert">
            <span>⚠️</span>
            <div><?= e($errors['general']) ?></div>
        </div>
    <?php endif; ?>

    <div class="card">
        <form action="<?= base_url('auth/change-password.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password"
                       id="current_password"
                       name="current_password"
                       class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                       placeholder="••••••••"
                       autocomplete="current-password"
                       required>
                <?php if (isset($errors['current_password'])): ?>
                    <div class="invalid-feedback"><?= e($errors['current_password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password"
                       id="new_password"
                       name="new_password"
                       class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                       placeholder="••••••••"
                       autocomplete="new-password"
                       required>
                <div class="form-text">Min 8 characters, uppercase, lowercase, number & special character.</div>
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

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    Update Password
                </button>
                <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-outline">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
