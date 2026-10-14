<?php
/**
 * profile.php – View and edit the logged-in user's profile.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db      = get_db();
$user    = current_user();
$errors  = [];
$success = false;

// Fetch fresh user data
$stmt = $db->prepare('SELECT * FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $user['user_id']]);
$userData = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fullName = sanitise_string($_POST['full_name'] ?? '');
    $phone    = sanitise_string($_POST['phone']     ?? '');
    $email    = sanitise_string($_POST['email']     ?? '');

    // Validate
    if (empty($fullName))            $errors['full_name'] = 'Full name is required.';
    elseif (strlen($fullName) > 120) $errors['full_name'] = 'Name must be 120 characters or fewer.';

    if (empty($email))               $errors['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                                      $errors['email'] = 'Enter a valid email address.';

    if (!empty($phone) && !preg_match('/^\+?[0-9 \-]{7,25}$/', $phone))
                                      $errors['phone'] = 'Enter a valid phone number.';

    // Check email uniqueness (excluding current user)
    if (empty($errors['email'])) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :uid');
        $stmt->execute([':email' => $email, ':uid' => $user['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'That email is already in use by another account.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            'UPDATE users SET full_name = :fn, email = :email, phone = :phone
              WHERE user_id = :uid'
        );
        $stmt->execute([
            ':fn'    => $fullName,
            ':email' => $email,
            ':phone' => $phone ?: null,
            ':uid'   => $user['user_id'],
        ]);

        // Update session
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email']     = $email;

        log_action('profile_updated', "User updated their profile.", $user['user_id']);
        flash('success', 'Profile updated successfully.');
        redirect(app_url('/auth/profile.php'));
    }

    // Re-populate on error
    $userData['full_name'] = $fullName;
    $userData['email']     = $email;
    $userData['phone']     = $phone;
}

$pageTitle = 'My Profile';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container" style="max-width:700px">
  <div class="page-header">
    <div>
      <h1>My Profile</h1>
      <p>Update your personal information</p>
    </div>
    <a href="<?= app_url('/auth/change-password.php') ?>" class="btn btn-secondary">
      🔐 Change Password
    </a>
  </div>

  <div class="card">
    <!-- Avatar display -->
    <div class="d-flex gap-4 align-center" style="margin-bottom:var(--sp-8)">
      <div class="avatar avatar-xl" aria-hidden="true">
        <?= e(mb_substr($userData['full_name'], 0, 1)) ?>
      </div>
      <div>
        <h2 style="margin-bottom:var(--sp-1)"><?= e($userData['full_name']) ?></h2>
        <span class="badge badge-orange"><?= e($user['role']) ?></span>
        <p style="margin-top:var(--sp-2);font-size:.85rem">
          Member since <?= date('F Y', strtotime($userData['created_at'])) ?>
        </p>
      </div>
    </div>

    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text"
               id="full_name"
               name="full_name"
               value="<?= e($userData['full_name']) ?>"
               autocomplete="name"
               required>
        <?php if (!empty($errors['full_name'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['full_name']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email"
               id="email"
               name="email"
               value="<?= e($userData['email']) ?>"
               autocomplete="email"
               required>
        <?php if (!empty($errors['email'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['email']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="phone">Phone Number <span style="color:var(--clr-text-subtle)">(optional)</span></label>
        <input type="tel"
               id="phone"
               name="phone"
               value="<?= e($userData['phone'] ?? '') ?>"
               autocomplete="tel"
               placeholder="+94 71 234 5678">
        <?php if (!empty($errors['phone'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['phone']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Read-only fields -->
      <div class="form-group">
        <label>Username</label>
        <input type="text" value="<?= e($userData['username']) ?>" disabled
               style="opacity:.6;cursor:not-allowed">
        <span class="form-hint">Username cannot be changed.</span>
      </div>

      <div class="form-group">
        <label>Role</label>
        <input type="text" value="<?= e($user['role']) ?>" disabled
               style="opacity:.6;cursor:not-allowed">
      </div>

      <button type="submit" class="btn btn-primary" id="save-profile-btn">
        💾 Save Changes
      </button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
