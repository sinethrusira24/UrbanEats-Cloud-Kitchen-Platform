<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Profile Management
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Authentication Guard
require_login();

$user = current_user();
if (!$user) {
    flash('danger', 'Unable to retrieve your user profile.');
    redirect('auth/login.php');
}

$errors = [];
$fullName = $user['full_name'];
$username = $user['username'];
$email    = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));

    // Validation: Full Name
    if ($fullName === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name must not exceed 100 characters.';
    }

    // Validation: Username
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors['username'] = 'Username must be 3-50 characters (letters, numbers, underscores).';
    }

    // Validation: Email
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Uniqueness checks (excluding current user)
    if (empty($errors['username']) || empty($errors['email'])) {
        try {
            $pdo = get_db_connection();

            if (empty($errors['username']) && strtolower($username) !== strtolower($user['username'])) {
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = :u AND user_id != :id LIMIT 1');
                $stmt->execute([':u' => $username, ':id' => (int)$user['user_id']]);
                if ($stmt->fetch()) {
                    $errors['username'] = 'This username is already taken by another account.';
                }
            }

            if (empty($errors['email']) && strtolower($email) !== strtolower($user['email'])) {
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :e AND user_id != :id LIMIT 1');
                $stmt->execute([':e' => $email, ':id' => (int)$user['user_id']]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'This email address is already in use by another account.';
                }
            }
        } catch (Throwable $e) {
            $errors['general'] = 'Database verification error. Please try again.';
        }
    }

    // Process update
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();
            $stmtUpdate = $pdo->prepare(
                'UPDATE users
                 SET full_name = :full_name, username = :username, email = :email, updated_at = NOW()
                 WHERE user_id = :user_id'
            );
            $stmtUpdate->execute([
                ':full_name' => $fullName,
                ':username'  => $username,
                ':email'     => $email,
                ':user_id'   => (int)$user['user_id'],
            ]);

            // Update session username
            $_SESSION['user_name'] = $username;

            log_action('profile_update', "User updated profile details (username: {$username}, email: {$email})", (int)$user['user_id']);

            flash('success', 'Your profile details have been successfully updated.');
            redirect('auth/profile.php');
        } catch (Throwable $e) {
            $errors['general'] = 'An error occurred while saving your profile changes.';
        }
    }
}

$pageTitle = 'My Profile';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="max-width: 760px; margin: 0 auto;">
    <div class="page-header">
        <div class="page-title">
            <h1>Account Profile</h1>
            <p class="page-subtitle">Manage your personal information and view account status</p>
        </div>
        <a href="<?= base_url('auth/change-password.php') ?>" class="btn btn-outline">
            Change Password
        </a>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" role="alert">
            <span>⚠️</span>
            <div><?= e($errors['general']) ?></div>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h2 style="font-size: 1.15rem; margin: 0;">Account Overview</h2>
            <span class="badge badge-<?= e($user['role_name']) ?>">
                <?= e(str_replace('_', ' ', (string)$user['role_name'])) ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; font-size: 0.925rem;">
            <div>
                <span style="color: var(--text-muted); font-size: 0.825rem; display: block;">User ID</span>
                <strong>#<?= (int)$user['user_id'] ?></strong>
            </div>
            <div>
                <span style="color: var(--text-muted); font-size: 0.825rem; display: block;">Account Status</span>
                <?php if ((int)$user['is_active'] === 1): ?>
                    <span class="badge badge-active">Active</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Suspended</span>
                <?php endif; ?>
            </div>
            <div>
                <span style="color: var(--text-muted); font-size: 0.825rem; display: block;">Member Since</span>
                <strong><?= date('M d, Y', strtotime($user['created_at'])) ?></strong>
            </div>
            <div>
                <span style="color: var(--text-muted); font-size: 0.825rem; display: block;">Role Description</span>
                <span style="color: var(--text-muted);"><?= e($user['role_description']) ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.15rem; margin: 0;">Edit Personal Details</h2>
        </div>

        <form action="<?= base_url('auth/profile.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text"
                       id="full_name"
                       name="full_name"
                       class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                       value="<?= e($fullName) ?>"
                       required>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text"
                           id="username"
                           name="username"
                           class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                           value="<?= e($username) ?>"
                           required>
                    <div class="form-text">Used for sign in. 3-50 alphanumeric characters.</div>
                    <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback"><?= e($errors['username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           value="<?= e($email) ?>"
                           required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
