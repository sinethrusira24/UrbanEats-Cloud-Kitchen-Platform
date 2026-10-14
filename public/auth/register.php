<?php
/**
 * register.php – Customer self-registration.
 *
 * Validates inputs server-side, hashes the password with password_hash(),
 * logs the action, and redirects to login on success.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

// Already logged in → go home
if (is_logged_in()) {
    redirect(app_url('/index.php'));
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // ── Collect & sanitise ─────────────────────────────
    $old['full_name'] = sanitise_string($_POST['full_name'] ?? '');
    $old['username']  = sanitise_string($_POST['username']  ?? '');
    $old['email']     = sanitise_string($_POST['email']     ?? '');
    $old['phone']     = sanitise_string($_POST['phone']     ?? '');
    $password         = $_POST['password']         ?? '';
    $confirm          = $_POST['confirm_password'] ?? '';

    // ── Validate ───────────────────────────────────────
    if (empty($old['full_name']))                $errors['full_name'] = 'Full name is required.';
    elseif (strlen($old['full_name']) > 120)     $errors['full_name'] = 'Name must be 120 characters or fewer.';

    if (empty($old['username']))                 $errors['username'] = 'Username is required.';
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $old['username']))
                                                  $errors['username'] = 'Username must be 3–50 characters (letters, numbers, underscores).';

    if (empty($old['email']))                    $errors['email'] = 'Email address is required.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
                                                  $errors['email'] = 'Enter a valid email address.';

    if (!empty($old['phone']) && !preg_match('/^\+?[0-9 \-]{7,25}$/', $old['phone']))
                                                  $errors['phone'] = 'Enter a valid phone number.';

    if (strlen($password) < 8)                   $errors['password'] = 'Password must be at least 8 characters.';
    elseif ($password !== $confirm)              $errors['password'] = 'Passwords do not match.';

    // ── Check uniqueness ───────────────────────────────
    if (empty($errors)) {
        $db = get_db();

        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute([':email' => $old['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'This email address is already registered.';
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $stmt->execute([':username' => $old['username']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['username'] = 'That username is already taken.';
        }
    }

    // ── Insert ─────────────────────────────────────────
    if (empty($errors)) {
        $db = get_db();

        // Fetch the Customer role_id
        $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
        $stmt->execute([':name' => 'Customer']);
        $roleRow = $stmt->fetch();

        if (!$roleRow) {
            $errors['general'] = 'Registration is temporarily unavailable. Please try later.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $db->prepare(
                'INSERT INTO users (role_id, full_name, username, email, password_hash, phone)
                 VALUES (:role_id, :full_name, :username, :email, :hash, :phone)'
            );
            $stmt->execute([
                ':role_id'   => $roleRow['role_id'],
                ':full_name' => $old['full_name'],
                ':username'  => $old['username'],
                ':email'     => $old['email'],
                ':hash'      => $hash,
                ':phone'     => $old['phone'] ?: null,
            ]);

            $newUserId = (int)$db->lastInsertId();
            log_action('user_registered', "New customer: {$old['username']} ({$old['email']})", $newUserId);

            flash('success', 'Account created! Please sign in.');
            redirect(app_url('/auth/login.php'));
        }
    }
}

$pageTitle = 'Create Account';
include __DIR__ . '/../../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Logo -->
    <div class="auth-logo">
      <div class="logo-icon" aria-hidden="true">🍽️</div>
      <h1>Create Account</h1>
      <p>Join UrbanEats and start ordering</p>
    </div>

    <!-- General error -->
    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-error" role="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate id="register-form">
      <?= csrf_field() ?>

      <!-- Full name -->
      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text"
               id="full_name"
               name="full_name"
               value="<?= e($old['full_name'] ?? '') ?>"
               autocomplete="name"
               required
               aria-describedby="full_name_err"
               placeholder="Jane Perera">
        <?php if (!empty($errors['full_name'])): ?>
          <span class="field-error" id="full_name_err" role="alert">⚠ <?= e($errors['full_name']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Username -->
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text"
               id="username"
               name="username"
               value="<?= e($old['username'] ?? '') ?>"
               autocomplete="username"
               required
               aria-describedby="username_err"
               placeholder="jane_perera">
        <span class="form-hint">Letters, numbers and underscores, 3–50 characters.</span>
        <?php if (!empty($errors['username'])): ?>
          <span class="field-error" id="username_err" role="alert">⚠ <?= e($errors['username']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email"
               id="email"
               name="email"
               value="<?= e($old['email'] ?? '') ?>"
               autocomplete="email"
               required
               aria-describedby="email_err"
               placeholder="jane@example.com">
        <?php if (!empty($errors['email'])): ?>
          <span class="field-error" id="email_err" role="alert">⚠ <?= e($errors['email']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Phone -->
      <div class="form-group">
        <label for="phone">Phone Number <span style="color:var(--clr-text-subtle)">(optional)</span></label>
        <input type="tel"
               id="phone"
               name="phone"
               value="<?= e($old['phone'] ?? '') ?>"
               autocomplete="tel"
               aria-describedby="phone_err"
               placeholder="+94 71 234 5678">
        <?php if (!empty($errors['phone'])): ?>
          <span class="field-error" id="phone_err" role="alert">⚠ <?= e($errors['phone']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label for="password">Password</label>
        <div class="d-flex gap-2" style="align-items:center">
          <input type="password"
                 id="password"
                 name="password"
                 autocomplete="new-password"
                 required
                 aria-describedby="password_err"
                 placeholder="Minimum 8 characters">
          <button type="button" class="btn-icon" data-toggle-pw="password" aria-label="Show password">👁</button>
        </div>
        <!-- Strength meter -->
        <div class="pw-strength-bar" aria-hidden="true">
          <div class="pw-strength-fill" id="pw-strength-fill"></div>
        </div>
        <span class="pw-strength-text" id="pw-strength-text" aria-live="polite"></span>
        <?php if (!empty($errors['password'])): ?>
          <span class="field-error" id="password_err" role="alert">⚠ <?= e($errors['password']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Confirm password -->
      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="d-flex gap-2" style="align-items:center">
          <input type="password"
                 id="confirm_password"
                 name="confirm_password"
                 autocomplete="new-password"
                 required
                 placeholder="Repeat your password">
          <button type="button" class="btn-icon" data-toggle-pw="confirm_password" aria-label="Show password">👁</button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" id="register-btn">
        Create Account
      </button>
    </form>

    <div class="auth-footer">
      Already have an account?
      <a href="<?= app_url('/auth/login.php') ?>">Sign in</a>
    </div>

  </div>
</div>

<?php
$extraScripts = <<<JS
<script>
// Client-side password match hint
(function(){
  const pw  = document.getElementById('password');
  const cpw = document.getElementById('confirm_password');
  if (!pw || !cpw) return;
  cpw.addEventListener('input', function() {
    if (cpw.value && cpw.value !== pw.value) {
      cpw.style.borderColor = 'var(--clr-error)';
    } else {
      cpw.style.borderColor = '';
    }
  });
})();
</script>
JS;
include __DIR__ . '/../../includes/footer.php';
?>
