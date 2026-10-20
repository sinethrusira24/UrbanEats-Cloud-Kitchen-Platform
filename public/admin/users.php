<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Administrator User Management
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Authorization Guard: strictly restricted to Administrator role
require_role('admin');

$pdo = get_db_connection();
$currentAdminId = current_user_id();

// Retrieve all available roles from the database for verification
$rolesStmt = $pdo->query('SELECT role_id, role_name, role_description FROM roles ORDER BY role_id ASC');
$allRoles = $rolesStmt->fetchAll();
$roleMap = [];
foreach ($allRoles as $r) {
    $roleMap[(int)$r['role_id']] = $r['role_name'];
}

// -----------------------------------------------------------------------------
// POST Actions: Activate, Deactivate, Change Role, Create User
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string)($_POST['action'] ?? '');

    // Action 1: Toggle Account Status (Activate / Deactivate)
    if ($action === 'toggle_status') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus    = (int)($_POST['new_status'] ?? 0); // 1 = activate, 0 = deactivate

        // Safety 1: Cannot modify non-existent user
        $stmtUser = $pdo->prepare('SELECT user_id, username, role_id, is_active FROM users WHERE user_id = :id LIMIT 1');
        $stmtUser->execute([':id' => $targetUserId]);
        $targetUser = $stmtUser->fetch();

        if (!$targetUser) {
            flash('danger', 'User account not found.');
            redirect('admin/users.php');
        }

        // Safety 2: Cannot deactivate self
        if ($targetUserId === $currentAdminId && $newStatus === 0) {
            flash('danger', 'Safety Protection: You cannot deactivate your own administrator account.');
            redirect('admin/users.php');
        }

        // Safety 3: Cannot deactivate the last active administrator
        if ($newStatus === 0 && (int)$targetUser['role_id'] === 1) {
            $adminCountStmt = $pdo->query('SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1');
            $activeAdminCount = (int)$adminCountStmt->fetchColumn();

            if ($activeAdminCount <= 1) {
                flash('danger', 'Safety Protection: Cannot deactivate the platform’s last active administrator.');
                redirect('admin/users.php');
            }
        }

        try {
            $stmtUpdate = $pdo->prepare('UPDATE users SET is_active = :status, updated_at = NOW() WHERE user_id = :id');
            $stmtUpdate->execute([':status' => $newStatus, ':id' => $targetUserId]);

            $logAction = $newStatus === 1 ? 'account_activation' : 'account_deactivation';
            $logMsg = sprintf(
                'Admin %s %s user %s (ID: %d)',
                $_SESSION['user_name'] ?? 'Admin',
                $newStatus === 1 ? 'activated' : 'deactivated',
                $targetUser['username'],
                $targetUserId
            );
            log_action($logAction, $logMsg, $currentAdminId);

            flash('success', "User '{$targetUser['username']}' status successfully updated to " . ($newStatus === 1 ? 'Active' : 'Suspended') . '.');
        } catch (Throwable $e) {
            flash('danger', 'Failed to update user status.');
        }
        redirect('admin/users.php');
    }

    // Action 2: Change User Role
    if ($action === 'change_role') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newRoleId    = (int)($_POST['new_role_id'] ?? 0);

        // Verify target role exists in database
        if (!isset($roleMap[$newRoleId])) {
            flash('danger', 'Invalid role selected.');
            redirect('admin/users.php');
        }

        $stmtUser = $pdo->prepare('SELECT user_id, username, role_id, is_active FROM users WHERE user_id = :id LIMIT 1');
        $stmtUser->execute([':id' => $targetUserId]);
        $targetUser = $stmtUser->fetch();

        if (!$targetUser) {
            flash('danger', 'Target user not found.');
            redirect('admin/users.php');
        }

        $oldRoleId = (int)$targetUser['role_id'];

        // Safety: If demoting an admin, ensure they are not the last active administrator
        if ($oldRoleId === 1 && $newRoleId !== 1) {
            $adminCountStmt = $pdo->query('SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1');
            $activeAdminCount = (int)$adminCountStmt->fetchColumn();

            if ($activeAdminCount <= 1) {
                flash('danger', 'Safety Protection: Cannot demote the last remaining administrator.');
                redirect('admin/users.php');
            }

            if ($targetUserId === $currentAdminId) {
                flash('danger', 'Safety Protection: You cannot demote your own administrator privileges.');
                redirect('admin/users.php');
            }
        }

        try {
            $stmtUpdate = $pdo->prepare('UPDATE users SET role_id = :role_id, updated_at = NOW() WHERE user_id = :id');
            $stmtUpdate->execute([':role_id' => $newRoleId, ':id' => $targetUserId]);

            $oldRoleName = $roleMap[$oldRoleId] ?? 'unknown';
            $newRoleName = $roleMap[$newRoleId] ?? 'unknown';

            $logMsg = sprintf(
                'Role updated for user %s (ID: %d) from %s to %s by admin %s',
                $targetUser['username'],
                $targetUserId,
                $oldRoleName,
                $newRoleName,
                $_SESSION['user_name'] ?? 'Admin'
            );
            log_action('role_change', $logMsg, $currentAdminId);

            flash('success', "Role for '{$targetUser['username']}' updated to " . str_replace('_', ' ', $newRoleName) . '.');
        } catch (Throwable $e) {
            flash('danger', 'Failed to update user role.');
        }
        redirect('admin/users.php');
    }

    // Action 3: Admin Create Account (Kitchen Staff or Administrator)
    if ($action === 'create_user') {
        $newFullName = trim((string)($_POST['new_full_name'] ?? ''));
        $newUsername = trim((string)($_POST['new_username'] ?? ''));
        $newEmail    = trim((string)($_POST['new_email'] ?? ''));
        $newRoleId   = (int)($_POST['new_role_id'] ?? 3);
        $newPassword = (string)($_POST['new_password'] ?? '');

        $createErrors = [];

        if ($newFullName === '') $createErrors[] = 'Full name is required.';
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $newUsername)) $createErrors[] = 'Valid username is required.';
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $createErrors[] = 'Valid email address is required.';
        if (!isset($roleMap[$newRoleId])) $createErrors[] = 'Invalid role selected.';

        $pwdErrors = validate_password_strength($newPassword);
        if (!empty($pwdErrors)) $createErrors = array_merge($createErrors, $pwdErrors);

        if (empty($createErrors)) {
            // Check uniqueness
            $stmtCheck = $pdo->prepare('SELECT user_id FROM users WHERE username = :u OR email = :e LIMIT 1');
            $stmtCheck->execute([':u' => $newUsername, ':e' => $newEmail]);
            if ($stmtCheck->fetch()) {
                $createErrors[] = 'Username or email already exists.';
            }
        }

        if (empty($createErrors)) {
            try {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO users (role_id, full_name, username, email, password_hash, is_active, created_at)
                     VALUES (:role_id, :full_name, :username, :email, :password_hash, 1, NOW())'
                );
                $stmtInsert->execute([
                    ':role_id'       => $newRoleId,
                    ':full_name'     => $newFullName,
                    ':username'      => $newUsername,
                    ':email'         => $newEmail,
                    ':password_hash' => $hash,
                ]);

                $newCreatedId = (int)$pdo->lastInsertId();
                $createdRoleName = $roleMap[$newRoleId] ?? 'user';
                log_action(
                    'user_create',
                    "Admin created {$createdRoleName} account: {$newUsername} ({$newEmail})",
                    $currentAdminId
                );

                flash('success', "New {$createdRoleName} account '{$newUsername}' created successfully.");
                redirect('admin/users.php');
            } catch (Throwable $e) {
                flash('danger', 'Failed to create user account.');
            }
        } else {
            flash('danger', 'Creation failed: ' . implode(' ', $createErrors));
        }
        redirect('admin/users.php');
    }
}

// -----------------------------------------------------------------------------
// GET Query Filters, Search, and Pagination
// -----------------------------------------------------------------------------
$search = trim((string)($_GET['search'] ?? ''));
$filterRole = trim((string)($_GET['role'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(u.full_name LIKE :search_name OR u.username LIKE :search_user OR u.email LIKE :search_email)';
    $params[':search_name'] = "%{$search}%";
    $params[':search_user'] = "%{$search}%";
    $params[':search_email'] = "%{$search}%";
}

if ($filterRole !== '' && in_array($filterRole, ['admin', 'kitchen_staff', 'customer'], true)) {
    $whereClauses[] = 'r.role_name = :role_name';
    $params[':role_name'] = $filterRole;
}

if ($filterStatus !== '' && in_array($filterStatus, ['1', '0'], true)) {
    $whereClauses[] = 'u.is_active = :status_val';
    $params[':status_val'] = (int)$filterStatus;
}

$whereSql = implode(' AND ', $whereClauses);

// Count total matching records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u INNER JOIN roles r ON u.role_id = r.role_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Compute pagination
$pagination = paginate($totalRecords, $perPage, $page, base_url('admin/users.php'), [
    'search' => $search,
    'role'   => $filterRole,
    'status' => $filterStatus,
]);

// Fetch paginated user records
$sql = "SELECT u.user_id, u.role_id, u.full_name, u.username, u.email, u.is_active, u.created_at,
               r.role_name, r.role_description
        FROM users u
        INNER JOIN roles r ON u.role_id = r.role_id
        WHERE {$whereSql}
        ORDER BY u.user_id DESC
        LIMIT :offset, :per_page";

$userListStmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $userListStmt->bindValue($key, $val);
}
$userListStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$userListStmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
$userListStmt->execute();
$users = $userListStmt->fetchAll();

$pageTitle = 'User Management';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>User Management</h1>
        <p class="page-subtitle">Search, filter, manage roles, and toggle access states</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('createUserCard').scrollIntoView({behavior: 'smooth'});">
        ➕ Add New User
    </button>
</div>

<!-- Search & Filtering Toolbar -->
<form action="<?= base_url('admin/users.php') ?>" method="GET" class="filter-bar">
    <div class="filter-item">
        <label for="search" class="form-label" style="font-size:0.8rem;">Search Name / Username / Email</label>
        <input type="text"
               id="search"
               name="search"
               class="form-control"
               placeholder="Search..."
               value="<?= e($search) ?>">
    </div>

    <div class="filter-item" style="max-width: 180px;">
        <label for="role" class="form-label" style="font-size:0.8rem;">Role</label>
        <select id="role" name="role" class="form-control">
            <option value="">All Roles</option>
            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Administrator</option>
            <option value="kitchen_staff" <?= $filterRole === 'kitchen_staff' ? 'selected' : '' ?>>Kitchen Staff</option>
            <option value="customer" <?= $filterRole === 'customer' ? 'selected' : '' ?>>Customer</option>
        </select>
    </div>

    <div class="filter-item" style="max-width: 160px;">
        <label for="status" class="form-label" style="font-size:0.8rem;">Status</label>
        <select id="status" name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active Only</option>
            <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Suspended Only</option>
        </select>
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-secondary">Apply Filters</button>
        <?php if ($search !== '' || $filterRole !== '' || $filterStatus !== ''): ?>
            <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline" title="Reset all filters">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Users Data Table -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User Details</th>
                <th>Role</th>
                <th>Status</th>
                <th>Registered</th>
                <th>Role Assignment</th>
                <th>Account Access</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                        No user accounts found matching the filter criteria.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--text-muted);">#<?= (int)$u['user_id'] ?></td>
                        <td>
                            <strong><?= e($u['full_name']) ?></strong><br>
                            <span style="font-size: 0.825rem; color: var(--primary); font-weight: 600;">@<?= e($u['username']) ?></span> &bull;
                            <span style="font-size: 0.825rem; color: var(--text-muted);"><?= e($u['email']) ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?= e($u['role_name']) ?>">
                                <?= e(str_replace('_', ' ', (string)$u['role_name'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.825rem; white-space: nowrap;">
                            <?= date('M d, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td>
                            <!-- Role Change Form -->
                            <form action="<?= base_url('admin/users.php') ?>" method="POST" style="display: flex; gap: 0.35rem; align-items: center; margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id'] ?>">

                                <select name="new_role_id" class="form-control" style="padding: 0.25rem 0.5rem; font-size: 0.825rem; width: auto;"
                                        <?= ((int)$u['user_id'] === $currentAdminId) ? 'disabled title="Cannot change your own role"' : '' ?>>
                                    <?php foreach ($allRoles as $roleOption): ?>
                                        <option value="<?= (int)$roleOption['role_id'] ?>" <?= ((int)$u['role_id'] === (int)$roleOption['role_id']) ? 'selected' : '' ?>>
                                            <?= e(str_replace('_', ' ', $roleOption['role_name'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <?php if ((int)$u['user_id'] !== $currentAdminId): ?>
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding: 0.25rem 0.5rem;" title="Save role change">
                                        Update
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td>
                            <!-- Activate / Deactivate Form -->
                            <?php if ((int)$u['user_id'] === $currentAdminId): ?>
                                <span style="font-size: 0.775rem; color: var(--text-muted); font-style: italic;">Current Session</span>
                            <?php else: ?>
                                <form action="<?= base_url('admin/users.php') ?>" method="POST" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id'] ?>">

                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <input type="hidden" name="new_status" value="0">
                                        <button type="submit"
                                                class="btn btn-danger btn-sm"
                                                style="padding: 0.25rem 0.65rem;"
                                                onclick="return confirm('Deactivate account for <?= e(addslashes($u['username'])) ?>? User will be blocked from logging in.');">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="1">
                                        <button type="submit"
                                                class="btn btn-primary btn-sm"
                                                style="padding: 0.25rem 0.65rem; background-color: var(--success); border-color: var(--success);">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Controls -->
<?php if ($pagination['total_pages'] > 1): ?>
    <ul class="pagination">
        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
            <a href="<?= $pagination['prev_url'] ?? '#' ?>" class="page-link">&laquo; Prev</a>
        </li>

        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
            <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                <a href="<?= ($pagination['build_url'])($p) ?>" class="page-link"><?= $p ?></a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
            <a href="<?= $pagination['next_url'] ?? '#' ?>" class="page-link">Next &raquo;</a>
        </li>
    </ul>
<?php endif; ?>

<!-- Create New User Section -->
<div class="card" id="createUserCard" style="margin-top: 3rem;">
    <div class="card-header">
        <h2 style="font-size: 1.15rem; margin: 0;">➕ Create System Account (Staff or Administrator)</h2>
    </div>
    <form action="<?= base_url('admin/users.php') ?>" method="POST" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">

        <div class="form-row">
            <div class="form-group">
                <label for="new_full_name" class="form-label">Full Name</label>
                <input type="text" id="new_full_name" name="new_full_name" class="form-control" placeholder="e.g. Kasun Fernando" required>
            </div>
            <div class="form-group">
                <label for="new_username" class="form-label">Username</label>
                <input type="text" id="new_username" name="new_username" class="form-control" placeholder="e.g. kasun_chef" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="new_email" class="form-label">Email Address</label>
                <input type="email" id="new_email" name="new_email" class="form-control" placeholder="staff@urbaneats.lk" required>
            </div>
            <div class="form-group">
                <label for="new_role_id" class="form-label">Assign Role</label>
                <select id="new_role_id" name="new_role_id" class="form-control" required>
                    <?php foreach ($allRoles as $role): ?>
                        <option value="<?= (int)$role['role_id'] ?>" <?= (int)$role['role_id'] === 2 ? 'selected' : '' ?>>
                            <?= e(str_replace('_', ' ', $role['role_name'])) ?> &mdash; <?= e($role['role_description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="new_password" class="form-label">Initial Password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min 8 chars with uppercase, lowercase, number, symbol" required>
            <div class="form-text">Must satisfy the platform password strength policy.</div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
            Create User Account
        </button>
    </form>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
