<?php
/**
 * login.php – User login with throttling.
 *
 * Locks the account identifier after MAX_LOGIN_ATTEMPTS consecutive failures
 * within the LOCKOUT_DURATION window. Regenerates the session ID on success
 * to prevent session fixation.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(app_url('/index.php'));
}

$errors     = [];
$lockout    = false;
$old_login  = '';

/**
 * Count recent failed login attempts for a given identifier.
 */
function count_recent_failures(PDO $db, string $identifier): int {
    $window = date('Y-m-d H:i:s', time() - (LOCKOUT_DURATION ?? 900));
    $stmt   = $db->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE login_identifier = :id
            AND was_successful   = 0
            AND attempted_at    >= :window'
    );
    $stmt->execute([':id' => $identifier, ':window' => $window]);
    return (int)$stmt->fetchColumn();
}

/**
 * Record a login attempt.
 */
function record_attempt(PDO $db, string $identifier, ?int $userId, bool $success): void {
    $stmt = $db->prepare(
        'INSERT INTO login_attempts (user_id, login_identifier, ip_address, was_successful)
         VALUES (:uid, :id, :ip, :ok)'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':id'  => mb_substr($identifier, 0, 254),
        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ok'  => $success ? 1 : 0,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $loginIdentifier = sanitise_string($_POST['login'] ?? '');   // email or username
    $password        = $_POST['password'] ?? '';
    $old_login       = $loginIdentifier;

    // ── Throttle check ─────────────────────────────────
    $db      = get_db();
    $maxAttempts = MAX_LOGIN_ATTEMPTS ?? 5;

    if (count_recent_failures($db, $loginIdentifier) >= $maxAttempts) {
        $lockout = true;
        $errors['general'] = 'Too many failed attempts. Please wait ' .
            ceil((LOCKOUT_DURATION ?? 900) / 60) . ' minutes and try again.';
    }

    if (!$lockout) {
        // ── Look up user ────────────────────────────────
        $stmt = $db->prepare(
            'SELECT u.user_id, u.password_hash, u.is_active, u.full_name,
                    u.username, u.email, r.role_name, u.role_id
               FROM users u
               JOIN roles r ON r.role_id = u.role_id
              WHERE u.email = :email_id OR u.username = :username_id
              LIMIT 1'
        );
        $stmt->execute([
            ':email_id'    => $loginIdentifier,
            ':username_id' => $loginIdentifier,
        ]);
        $user = $stmt->fetch();

        // ── Verify ──────────────────────────────────────
        if (!$user || !password_verify($password, $user['password_hash'])) {
            record_attempt($db, $loginIdentifier, $user['user_id'] ?? null, false);
            $remaining = $maxAttempts - count_recent_failures($db, $loginIdentifier);
            $errors['general'] = 'Invalid username/email or password.' .
                ($remaining <= 2 && $remaining > 0 ? " ($remaining attempts left)" : '');
        } elseif (!$user['is_active']) {
            record_attempt($db, $loginIdentifier, $user['user_id'], false);
            $errors['general'] = 'Your account has been deactivated. Please contact an administrator.';
        } else {
            // ── Success ─────────────────────────────────
            record_attempt($db, $loginIdentifier, $user['user_id'], true);

            // Regenerate session to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role_name'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['last_activity'] = time();

            log_action('user_login', "User {$user['username']} signed in.", $user['user_id']);

            // Redirect to intended page or role dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            if ($redirect && str_starts_with($redirect, '/')) {
                redirect($redirect);
            }

            // Role-based default redirect
            $roleRedirect = match ($user['role_name']) {
                'Administrator' => app_url('/admin/dashboard.php'),
                'Kitchen Staff' => app_url('/orders/kitchen.php'),
                default         => app_url('/index.php'),
            };
            redirect($roleRedirect);
        }
    }
}

$pageTitle = 'Sign In';
include __DIR__ . '/../../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-icon" aria-hidden="true">🍽️</div>
      <h1>Welcome Back</h1>
      <p>Sign in to your UrbanEats account</p>
    </div>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-error" role="alert" aria-live="assertive">
        <?= e($errors['general']) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate id="login-form">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="login">Email or Username</label>
        <input type="text"
               id="login"
               name="login"
               value="<?= e($old_login) ?>"
               autocomplete="username"
               required
               <?= $lockout ? 'disabled' : '' ?>
               placeholder="jane@example.com">
      </div>

      <div class="form-group">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-2)">
          <label for="password" style="margin-bottom:0">Password</label>
          <a href="<?= app_url('/auth/reset-request.php') ?>" style="font-size:.85rem">Forgot password?</a>
        </div>
        <div class="d-flex gap-2" style="align-items:center">
          <input type="password"
                 id="password"
                 name="password"
                 autocomplete="current-password"
                 required
                 <?= $lockout ? 'disabled' : '' ?>
                 placeholder="Your password">
          <button type="button" class="btn-icon" data-toggle-pw="password" aria-label="Show password">👁</button>
        </div>
      </div>

      <button type="submit"
              class="btn btn-primary btn-full btn-lg"
              id="login-btn"
              <?= $lockout ? 'disabled' : '' ?>>
        Sign In
      </button>
    </form>

    <div class="auth-footer">
      Don't have an account?
      <a href="<?= app_url('/auth/register.php') ?>">Create one</a>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
