<?php
/**
 * reset-password.php – Consume a password reset token and set a new password.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in()) {
    redirect(app_url('/index.php'));
}

$db          = get_db();
$rawToken    = sanitise_string($_GET['token'] ?? '');
$tokenRecord = null;
$errors      = [];

// Validate the token from the URL
if ($rawToken) {
    $tokenHash = hash('sha256', $rawToken);
    $stmt = $db->prepare(
        'SELECT pr.*, u.email FROM password_resets pr
           JOIN users u ON u.user_id = pr.user_id
          WHERE pr.token_hash = :hash
            AND pr.used_at IS NULL
            AND pr.expires_at > NOW()
          LIMIT 1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $tokenRecord = $stmt->fetch();
}

if (!$tokenRecord) {
    flash('error', 'This reset link is invalid or has expired. Please request a new one.');
    redirect(app_url('/auth/reset-request.php'));
}

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $newPw     = $_POST['new_password']     ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (strlen($newPw) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters.';
    } elseif ($newPw !== $confirmPw) {
        $errors['new_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :uid')
               ->execute([':hash' => $hash, ':uid' => $tokenRecord['user_id']]);

            $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_id = :rid")
               ->execute([':rid' => $tokenRecord['reset_id']]);

            $db->commit();

            log_action('password_reset_completed', "Password reset via token for user {$tokenRecord['user_id']}", $tokenRecord['user_id']);
            flash('success', 'Password reset successful! Please sign in with your new password.');
            redirect(app_url('/auth/login.php'));
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[UrbanEats] Password reset failed: ' . $e->getMessage());
            $errors['general'] = 'An error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Set New Password';
include __DIR__ . '/../../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-icon" aria-hidden="true">🔒</div>
      <h1>Set New Password</h1>
      <p>For <?= e($tokenRecord['email']) ?></p>
    </div>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-error" role="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($rawToken) ?>">

      <div class="form-group">
        <label for="new_password">New Password</label>
        <div class="d-flex gap-2" style="align-items:center">
          <input type="password"
                 id="new_password"
                 name="new_password"
                 autocomplete="new-password"
                 required
                 placeholder="Minimum 8 characters">
          <button type="button" class="btn-icon" data-toggle-pw="new_password" aria-label="Show password">👁</button>
        </div>
        <div class="pw-strength-bar" aria-hidden="true">
          <div class="pw-strength-fill" id="pw-strength-fill"></div>
        </div>
        <span class="pw-strength-text" id="pw-strength-text" aria-live="polite"></span>
        <?php if (!empty($errors['new_password'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['new_password']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <div class="d-flex gap-2" style="align-items:center">
          <input type="password"
                 id="confirm_password"
                 name="confirm_password"
                 autocomplete="new-password"
                 required
                 placeholder="Repeat your new password">
          <button type="button" class="btn-icon" data-toggle-pw="confirm_password" aria-label="Show password">👁</button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" id="set-pw-btn">
        🔒 Save New Password
      </button>
    </form>

  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
