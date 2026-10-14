<?php
/**
 * reset-request.php – Request a password reset link.
 *
 * Generates a cryptographically secure token, stores its SHA-256 hash in
 * the password_resets table, and shows the reset link on-screen (no email
 * required per assignment spec).
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in()) {
    redirect(app_url('/index.php'));
}

$errors      = [];
$resetLink   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = sanitise_string($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (empty($errors)) {
        $db   = get_db();
        $stmt = $db->prepare('SELECT user_id FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row  = $stmt->fetch();

        // Always show a "success" message to prevent email enumeration
        if ($row) {
            // Invalidate any previous active tokens for this user
            $db->prepare('DELETE FROM password_resets WHERE user_id = :uid')
               ->execute([':uid' => $row['user_id']]);

            // Generate token (raw = shown to user, hash = stored in DB)
            $rawToken  = bin2hex(random_bytes(32));          // 64-char hex
            $tokenHash = hash('sha256', $rawToken);
            $expires   = date('Y-m-d H:i:s', time() + (RESET_TOKEN_EXPIRY ?? 3600));

            $stmt = $db->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:uid, :hash, :exp)'
            );
            $stmt->execute([
                ':uid'  => $row['user_id'],
                ':hash' => $tokenHash,
                ':exp'  => $expires,
            ]);

            log_action('password_reset_requested', "Reset requested for email: $email", $row['user_id']);

            $resetLink = app_url('/auth/reset-password.php?token=' . urlencode($rawToken));
        }

        // Show success regardless of whether email was found
        flash('info', 'If that email address is registered, a reset link has been generated below.');
        // Don't redirect — show link on this page
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/../../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-icon" aria-hidden="true">🔑</div>
      <h1>Reset Password</h1>
      <p>Enter your email to get a reset link</p>
    </div>

    <?php if ($resetLink): ?>
      <div class="alert alert-success" role="alert">
        <div>
          <strong>Reset link generated!</strong><br>
          <small>In a real system this would be emailed. For testing, copy the link below:</small>
        </div>
      </div>
      <div style="background:var(--clr-bg-input);border:1px solid var(--clr-border);border-radius:var(--radius-md);padding:var(--sp-4);margin-bottom:var(--sp-5);word-break:break-all;font-size:.85rem">
        <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors['email'])): ?>
      <div class="alert alert-error" role="alert"><?= e($errors['email']) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email"
               id="email"
               name="email"
               autocomplete="email"
               required
               placeholder="jane@example.com">
      </div>

      <button type="submit" class="btn btn-primary btn-full" id="reset-request-btn">
        Send Reset Link
      </button>
    </form>

    <div class="auth-footer">
      Remembered it?
      <a href="<?= app_url('/auth/login.php') ?>">Sign in</a>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
