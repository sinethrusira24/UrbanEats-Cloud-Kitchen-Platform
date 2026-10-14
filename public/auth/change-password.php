<?php
/**
 * change-password.php – Authenticated password change.
 *
 * Requires the user to supply their current password for confirmation.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db     = get_db();
$user   = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $currentPw  = $_POST['current_password']  ?? '';
    $newPw      = $_POST['new_password']       ?? '';
    $confirmPw  = $_POST['confirm_password']   ?? '';

    // Fetch current hash
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE user_id = :uid');
    $stmt->execute([':uid' => $user['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPw, $row['password_hash'])) {
        $errors['current_password'] = 'Current password is incorrect.';
    }

    if (strlen($newPw) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    } elseif ($newPw !== $confirmPw) {
        $errors['new_password'] = 'Passwords do not match.';
    } elseif (password_verify($newPw, $row['password_hash'] ?? '')) {
        $errors['new_password'] = 'New password must be different from your current password.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :uid');
        $stmt->execute([':hash' => $hash, ':uid' => $user['user_id']]);

        log_action('password_changed', 'User changed their own password.', $user['user_id']);
        flash('success', 'Password changed successfully.');
        redirect(app_url('/auth/profile.php'));
    }
}

$pageTitle = 'Change Password';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container" style="max-width:520px">
  <div class="page-header">
    <div>
      <h1>Change Password</h1>
      <p>Keep your account secure with a strong password.</p>
    </div>
  </div>

  <div class="card">
    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="current_password">Current Password</label>
        <div class="d-flex gap-2" style="align-items:center">
          <input type="password"
                 id="current_password"
                 name="current_password"
                 autocomplete="current-password"
                 required
                 placeholder="Your current password">
          <button type="button" class="btn-icon" data-toggle-pw="current_password" aria-label="Show password">👁</button>
        </div>
        <?php if (!empty($errors['current_password'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['current_password']) ?></span>
        <?php endif; ?>
      </div>

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

      <div class="d-flex gap-4" style="flex-wrap:wrap">
        <button type="submit" class="btn btn-primary" id="change-pw-btn">🔐 Update Password</button>
        <a href="<?= app_url('/auth/profile.php') ?>" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
