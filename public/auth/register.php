<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Registration
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect(current_user_role() === 'admin' ? 'admin/index.php' : 'menu/index.php');
}

$errors = [];
$fullName = '';
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName        = trim((string)($_POST['full_name'] ?? ''));
    $username        = trim((string)($_POST['username'] ?? ''));
    $email           = trim((string)($_POST['email'] ?? ''));
    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // Validation 1: Full Name
    if ($fullName === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name must not exceed 100 characters.';
    }

    // Validation 2: Username
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors['username'] = 'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
    }

    // Validation 3: Email
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Validation 4: Password Strength
    $pwdErrors = validate_password_strength($password);
    if (!empty($pwdErrors)) {
        $errors['password'] = implode(' ', $pwdErrors);
    }

    // Validation 5: Password Confirmation
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Uniqueness Checks (if syntax validations passed)
    if (empty($errors['username']) || empty($errors['email'])) {
        try {
            $pdo = get_db_connection();

            if (empty($errors['username'])) {
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = :username LIMIT 1');
                $stmt->execute([':username' => $username]);
                if ($stmt->fetch()) {
                    $errors['username'] = 'This username is already taken. Please choose another.';
                }
            }

            if (empty($errors['email'])) {
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'An account with this email address already exists.';
                }
            }
        } catch (Throwable $e) {
            $errors['general'] = 'Database error occurred. Please try again later.';
        }
    }

    // Insertion
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            // Retrieve Customer role ID (role_id for 'customer')
            $stmtRole = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'customer' LIMIT 1");
            $stmtRole->execute();
            $roleId = (int)($stmtRole->fetchColumn() ?: 3);

            // Hash password with bcrypt cost 12
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmtInsert = $pdo->prepare(
                'INSERT INTO users (role_id, full_name, username, email, password_hash, is_active, created_at)
                 VALUES (:role_id, :full_name, :username, :email, :password_hash, 1, NOW())'
            );

            $stmtInsert->execute([
                ':role_id'       => $roleId,
                ':full_name'     => $fullName,
                ':username'      => $username,
                ':email'         => $email,
                ':password_hash' => $passwordHash,
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // Audit Log
            log_action('user_registration', "Customer account registered: {$username} ({$email})", $newUserId);

            // Clear any lingering target URL so new user logs straight into menu
            unset($_SESSION['redirect_after_login']);

            flash('success', 'Registration successful! You may now sign in with your credentials.');
            redirect('auth/login.php');
        } catch (Throwable $e) {
            $errors['general'] = 'Failed to create your account. Please try again.';
        }
    }
}

$pageTitle = 'Create Account';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo-wrapper">
                <a href="<?= base_url('index.php') ?>" title="Return to Home">
                    <img src="<?= base_url('images/logo.png') ?>" alt="UrbanEats Logo" class="auth-logo-img">
                </a>
            </div>
            <h1>Create Account</h1>
            <p>Join UrbanEats Cloud Kitchen Platform</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠️</span>
                <div><?= e($errors['general']) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('auth/register.php') ?>" method="POST" novalidate>
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

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text"
                       id="username"
                       name="username"
                       class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                       value="<?= e($username) ?>"
                       autocomplete="username"
                       required>
                <div class="form-text">3-50 alphanumeric characters or underscores.</div>
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
                       autocomplete="email"
                       required>
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-input-wrapper">
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           autocomplete="new-password"
                           required>
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password" tabindex="-1">
                        <svg class="eye-icon eye-show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-icon eye-hide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <div class="form-text">Min 8 chars, with uppercase, lowercase, number & special character.</div>
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-input-wrapper">
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                           autocomplete="new-password"
                           required>
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password" tabindex="-1">
                        <svg class="eye-icon eye-show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-icon eye-hide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= base_url('auth/login.php') ?>">Sign in here</a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
